<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use App\Exceptions\ChannelAuthorizationException;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FacebookOAuth
{
    public const array SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'];

    private const array CONTENT_TASKS = ['CREATE_CONTENT', 'PROFILE_PLUS_CREATE_CONTENT'];

    public static function graph(): string
    {
        return 'https://graph.facebook.com/' . config()->string('services.facebook.version');
    }

    public function discover(Channel $channel, string $code, string $redirect): void
    {
        Cache::forget($this->key($channel));
        session()->forget('facebook_selection.' . $channel->id);
        $response = Http::asForm()->withoutRedirecting()->timeout(20)->post(self::graph() . '/oauth/access_token', [
            'client_id' => $channel->oauthClientId(), 'client_secret' => $channel->oauthClientSecret(),
            'redirect_uri' => $redirect, 'code' => $code,
        ])->throw();
        $userToken = Payload::string($response->json('access_token'), 'access_token');
        $client = Http::withToken($userToken)->withoutRedirecting()->timeout(20)->baseUrl(self::graph());
        $granted = $client->get('me/permissions')->throw()->collect('data')->where('status', 'granted')->pluck('permission')->all();

        if (array_any(self::SCOPES, fn(string $scope): bool => ! in_array($scope, $granted, true))) {
            throw new ChannelAuthorizationException('Для подключения страницы нужны все три разрешения Pages.');
        }

        $pages = [];
        $seen = [];
        $listIsEmpty = true;
        $query = ['fields' => 'id,name,tasks,access_token'];
        do {
            $result = $client->get('me/accounts', $query)->throw();

            $rows = $result->collect('data');
            $listIsEmpty = $listIsEmpty && $result->json('data') === [];

            foreach ($rows as $page) {
                if (! is_array($page) || ! is_array($page['tasks'] ?? null)) {
                    continue;
                }

                $id = Payload::string($page['id'] ?? null, 'id');

                $canCreateContent = array_any(self::CONTENT_TASKS, fn(string $task): bool => in_array($task, $page['tasks'], true));

                if (! $canCreateContent || ($channel->external_account !== null && $channel->external_account !== $id)) {
                    continue;
                }

                $pages[$id] = ['channel_id' => $id, 'account_name' => Payload::string($page['name'] ?? null, 'name'),
                    'access_token' => Payload::string($page['access_token'] ?? null, 'access_token'), 'granted_scopes' => self::SCOPES];
            }

            $after = $result->json('paging.cursors.after');

            if (! $result->json('paging.next') || ! is_string($after) || isset($seen[$after])) {
                break;
            }

            $seen[$after] = true;
            $query['after'] = $after;
        } while (true);

        if ($listIsEmpty) {
            $direct = $this->directPage($channel, $client, $userToken);

            if ($direct !== null) {
                $pages[$direct['channel_id']] = $direct;
            }
        }

        if ($pages === []) {
            throw new ChannelAuthorizationException('Нет доступной страницы с правом создания контента.');
        }

        session()->put('facebook_selection.' . $channel->id, Str::random(64));
        Cache::put($this->key($channel), Crypt::encrypt($pages), now()->addMinutes(10));
    }

    /** @return array{channel_id: string, account_name: string, access_token: string, granted_scopes: list<string>}|null */
    private function directPage(Channel $channel, PendingRequest $client, string $userToken): ?array
    {
        $id = config()->string('services.facebook.direct_page_id');

        if (! ctype_digit($id) || config()->string('services.facebook.direct_page_channel_id') !== (string) $channel->id
            || ($channel->external_account !== null && $channel->external_account !== $id)) {
            return null;
        }

        try {
            $appId = $channel->oauthClientId();
            $appSecret = $channel->oauthClientSecret();

            if ($appId === null || $appId === '' || $appSecret === null || $appSecret === '') {
                return null;
            }

            $inspector = Http::withToken($appId . '|' . $appSecret)
                ->withoutRedirecting()->timeout(8)->baseUrl(self::graph());
            $user = $inspector->get('debug_token', ['input_token' => $userToken]);

            if (! $user->ok() || ! $this->canManageDirectPage($user->json('data'), $appId, $id)) {
                return null;
            }

            $response = $client->get($id, ['fields' => 'id,name,access_token']);
            $name = $response->json('name');
            $token = $response->json('access_token');

            if (! $response->ok() || $response->json('id') !== $id || ! is_string($name) || $name === '' || ! is_string($token) || $token === '') {
                return null;
            }

            $page = $inspector->get('debug_token', ['input_token' => $token]);

            if (! $page->ok() || $page->json('data.type') !== 'PAGE' || ! in_array($page->json('data.profile_id'), [$id, (int) $id], true)
                || ! $this->canManageDirectPage($page->json('data'), $appId, $id)) {
                return null;
            }

            return ['channel_id' => $id, 'account_name' => $name, 'access_token' => $token, 'granted_scopes' => self::SCOPES];
        } catch (ConnectionException) {
            // Do not propagate debug_token URLs, which contain one of the tokens.
            return null;
        }
    }

    private function canManageDirectPage(mixed $data, string $appId, string $id): bool
    {
        if (! is_array($data) || ($data['is_valid'] ?? null) !== true || ! in_array($data['app_id'] ?? null, [$appId, (int) $appId], true)
            || ! is_array($data['granular_scopes'] ?? null)) {
            return false;
        }

        return array_any($data['granular_scopes'], fn(mixed $scope): bool => is_array($scope)
            && ($scope['scope'] ?? null) === 'pages_manage_posts'
            && is_array($scope['target_ids'] ?? null) && (in_array($id, $scope['target_ids'], true) || in_array((int) $id, $scope['target_ids'], true)));
    }

    /** @return list<array{id: string, name: string}> */
    public function options(Channel $channel): array
    {
        $value = Cache::get($this->key($channel));
        $options = [];

        foreach ($this->cachedPages($value) as $page) {
            $options[] = ['id' => $page['channel_id'], 'name' => $page['account_name']];
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function select(Channel $channel, string $page): array
    {
        $value = Cache::pull($this->key($channel));
        session()->forget('facebook_selection.' . $channel->id);
        $credentials = $this->cachedPages($value)[$page] ?? null;

        if (! is_array($credentials)) {
            throw ValidationException::withMessages(['oauth' => 'Выбор страницы истёк или страница недоступна.']);
        }

        return $credentials;
    }

    /** @return array<int|string, array{channel_id: string, account_name: string, access_token: string, granted_scopes: list<string>}> */
    private function cachedPages(mixed $encrypted): array
    {
        if (! is_string($encrypted)) {
            return [];
        }

        /** @var array<int|string, array{channel_id: string, account_name: string, access_token: string, granted_scopes: list<string>}> $pages */
        $pages = Crypt::decrypt($encrypted);

        return $pages;
    }

    private function key(Channel $channel): string
    {
        $nonce = session()->get('facebook_selection.' . $channel->id);

        return 'facebook-pages:' . hash('sha256', (is_string($nonce) ? $nonce : '') . ChannelOAuth::fingerprint($channel));
    }
}
