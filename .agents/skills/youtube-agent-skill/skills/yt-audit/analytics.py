#!/usr/bin/env python3
"""Read the channel owner's YouTube Analytics via a separate desktop OAuth grant."""
import argparse
import base64
from datetime import date, datetime, timedelta
import hashlib
from http.server import BaseHTTPRequestHandler, HTTPServer
import json
import os
from pathlib import Path
import re
import secrets
import sys
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, urlencode, urlsplit
from urllib.request import Request, urlopen
import webbrowser
from zoneinfo import ZoneInfo


PRIVATE = Path(__file__).resolve().parents[5] / "storage/app/private/youtube"
TOKEN_FILE = PRIVATE / "analytics-refresh.json"
SCOPES = {"https://www.googleapis.com/auth/youtube.readonly",
          "https://www.googleapis.com/auth/yt-analytics.readonly"}
METRICS = ("views,engagedViews,averageViewDuration,averageViewPercentage,"
           "likes,comments,shares,subscribersGained")
TOKEN_URL = "https://oauth2.googleapis.com/token"


def google(url, *, params=None, form=None, token=None):
    headers = {"Authorization": f"Bearer {token}"} if token else {}
    if params:
        url += "?" + urlencode(params)
    data = urlencode(form).encode() if form else None
    try:
        with urlopen(Request(url, data=data, headers=headers), timeout=25) as response:
            return json.load(response)
    except HTTPError as error:
        # Google's body may include request details. Return only the error identifier.
        try:
            detail = json.load(error)["error"]
            reason = detail if isinstance(detail, str) else detail.get("status", "error")
        except (KeyError, ValueError, AttributeError):
            reason = "error"
        raise ValueError(f"Google HTTP {error.code}: {reason}. Check API access and consent.") from None


def save_grant(grant):
    PRIVATE.mkdir(mode=0o700, parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", dir=PRIVATE, delete=False) as out:
        try:
            json.dump(grant, out)
            out.flush()
            os.fsync(out.fileno())
            os.replace(out.name, TOKEN_FILE)
        finally:
            Path(out.name).unlink(missing_ok=True)


def authorize(client, expected_channel):
    state = secrets.token_urlsafe(32)
    verifier = secrets.token_urlsafe(56)
    challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b"=").decode()
    result = {}

    class Callback(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass  # The URL contains a single-use authorization code.

        def do_GET(self):
            target = urlsplit(self.path)
            query = parse_qs(target.query)
            if target.path != "/" or not secrets.compare_digest(query.get("state", [""])[0], state):
                self.send_error(400)
                return
            result.update(query)
            self.send_response(200)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.send_header("Cache-Control", "no-store")
            self.end_headers()
            self.wfile.write(b"Return to the terminal to see the authorization result.")

    with HTTPServer(("127.0.0.1", 0), Callback) as server:
        redirect = f"http://127.0.0.1:{server.server_port}/"
        url = "https://accounts.google.com/o/oauth2/v2/auth?" + urlencode({
            "client_id": client["client_id"], "redirect_uri": redirect, "scope": " ".join(sorted(SCOPES)),
            "response_type": "code", "state": state, "code_challenge_method": "S256", "code_challenge": challenge,
            "access_type": "offline", "prompt": "consent select_account",
        })
        print(f"Choose the YouTube channel to audit in your local browser:\n{url}", flush=True, file=sys.stderr)
        webbrowser.open(url)
        server.timeout = 1
        deadline = time.monotonic() + 300
        while not result and time.monotonic() < deadline:
            server.handle_request()
    if not result.get("code"):
        raise ValueError("Authorization cancelled or timed out.")
    grant = google(TOKEN_URL, form={**client, "code": result["code"][0], "code_verifier": verifier,
                                   "redirect_uri": redirect, "grant_type": "authorization_code"})
    if not SCOPES.issubset(grant.get("scope", "").split()) or not grant.get("refresh_token"):
        raise ValueError("Google did not grant both read permissions and offline access.")
    channels = google("https://www.googleapis.com/youtube/v3/channels", token=grant["access_token"],
                      params={"part": "snippet", "mine": "true"}).get("items", [])
    matches = [ch for ch in channels if expected_channel is None or ch["id"] == expected_channel]
    if len(matches) != 1:
        raise ValueError("Choose the intended channel in Google and retry with --channel CHANNEL_ID.")
    channel = matches[0]
    save_grant({"client_id": client["client_id"], "refresh_token": grant["refresh_token"],
                "channel": channel["id"], "name": channel["snippet"]["title"]})
    print(f"Connected {channel['snippet']['title']} ({channel['id']}).")


def report(client, args):
    if args.start > args.end:
        raise ValueError("Start date must precede or equal end date.")
    if any(not re.fullmatch(r"[A-Za-z0-9_-]{11}", video) for video in args.videos):
        raise ValueError("Supply the 11-character YouTube video IDs.")
    grant = json.loads(TOKEN_FILE.read_text())
    if grant["client_id"] != client["client_id"]:
        raise ValueError("The saved grant belongs to another desktop client. Run auth again.")
    token = google(TOKEN_URL, form={**client, "refresh_token": grant["refresh_token"],
                                   "grant_type": "refresh_token"})["access_token"]
    common = {"ids": "channel==" + grant["channel"], "startDate": args.start, "endDate": args.end}
    videos = {}
    for video in dict.fromkeys(args.videos):
        params = {**common, "filters": "video==" + video, "metrics": METRICS}
        if args.retention:
            params.update(dimensions="elapsedVideoTimeRatio", metrics="audienceWatchRatio")
        response = google("https://youtubeanalytics.googleapis.com/v2/reports", params=params, token=token)
        columns = [col["name"] for col in response["columnHeaders"]]
        expected = ["elapsedVideoTimeRatio", "audienceWatchRatio"] if args.retention else METRICS.split(",")
        if columns != expected:
            raise ValueError("Unexpected YouTube report columns.")
        videos[video] = [dict(zip(columns, row, strict=True)) for row in response.get("rows", [])]
    return {"channel": grant["channel"], "name": grant["name"], "start": str(args.start),
            "end": str(args.end), "timeZone": "America/Los_Angeles", "videos": videos}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--client", type=Path, default=PRIVATE / "desktop-client.json")
    commands = parser.add_subparsers(dest="command", required=True)
    auth = commands.add_parser("auth", help="Connect a channel through a local browser")
    auth.add_argument("--channel", help="Expected YouTube channel ID (UC...)")
    stats = commands.add_parser("report", help="Write a JSON analytics report to stdout")
    stats.add_argument("videos", nargs="+")
    today = datetime.now(ZoneInfo("America/Los_Angeles")).date()
    stats.add_argument("--start", type=date.fromisoformat, default=today - timedelta(days=27))
    stats.add_argument("--end", type=date.fromisoformat, default=today)
    stats.add_argument("--retention", action="store_true", help="Return watch ratios along the timeline")
    args = parser.parse_args()
    try:
        installed = json.loads(args.client.read_text())["installed"]
        client = {key: installed[key] for key in ("client_id", "client_secret")}
        if args.command == "auth":
            authorize(client, args.channel)
        else:
            print(json.dumps(report(client, args), ensure_ascii=False, indent=2))
    except (KeyError, ValueError, OSError, URLError) as error:
        parser.exit(1, f"YouTube Analytics: {error}\n")


if __name__ == "__main__":
    main()
