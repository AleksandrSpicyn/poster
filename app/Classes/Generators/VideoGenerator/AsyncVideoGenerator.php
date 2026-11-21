<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Data\VideoResultData;
use App\Exceptions\VideoGenerationPendingException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Support\Integrations\Payload;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use UnexpectedValueException;

/** Потерянный ответ при создании задачи может означать списание. Повторять безопасно только опрос. */
abstract readonly class AsyncVideoGenerator implements VideoGenerator
{
    /** @param Closure():Response $send */
    final protected function submit(Closure $send, string $idField): string
    {
        try {
            return Payload::string($send()->json($idField), $idField);
        } catch (ConnectionException|UnexpectedValueException $exception) {
            throw new VideoGenerationUncertainException('Video submission outcome is unknown.', $exception->getCode(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->serverError()) {
                throw new VideoGenerationUncertainException('Video submission outcome is unknown.', $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }
    }

    final public function check(string $id): ?VideoResultData
    {
        try {
            return $this->poll($id);
        } catch (ConnectionException $exception) {
            throw new VideoGenerationPendingException('Video status is temporarily unavailable.', $exception->getCode(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 429 || $exception->response->serverError()) {
                throw new VideoGenerationPendingException('Video status is temporarily unavailable.', $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }
    }

    abstract protected function poll(string $id): ?VideoResultData;
}
