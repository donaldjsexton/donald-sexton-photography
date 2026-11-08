# CMS MCP interface

Remote clients can manage wedding-story drafts through the Model Context Protocol server already provided by `laravel/mcp`. This document is the setup guide. It does not deploy the server or create a live credential.

## Transport

The endpoint is `POST /mcp/cms`.

`Mcp::web()` is Laravel's Streamable HTTP transport. It accepts one JSON-RPC message per POST and answers `application/json`. `GET` and `DELETE` answer `405` with `Allow: POST`, so the server does not keep an SSE listening stream. That matches the MCP Streamable HTTP rule that a response may be a single JSON body. Supported protocol versions are the ones declared by the installed Laravel MCP server, including `2025-06-18` and `2025-11-25`.

Send:

```http
POST /mcp/cms
Accept: application/json, text/event-stream
Content-Type: application/json
Authorization: Bearer dsp_mcp_...
```

There is no separate unofficial JSON endpoint. Tool calls use `tools/call`.

## Authentication

Credentials are separate from admin passwords. `cms-mcp:issue` prints a bearer token once. The database stores only `sha256` of that token. Nothing in source or the application log should contain it. Do not commit command output.

Abilities:

| Ability | Allows |
| --- | --- |
| `stories:read` | `lookup_story` |
| `stories:draft` | `create_draft_story`, `update_draft_story` |
| `media:upload` | `upload_media` |
| `stories:publish` | `publish_story` only |

The default issue command grants read, draft, and upload. It does not grant publish. Add `--publish` or `--ability=stories:publish` when that credential should be able to make a story public.

```bash
php artisan cms-mcp:issue "Grok Bot"
php artisan cms-mcp:issue "Grok Bot publish" --publish
php artisan cms-mcp:revoke 1
```

A missing, wrong, expired, or revoked token gets HTTP 401. A valid token without the ability gets a tool error and does not change data.

`create_draft_story` and `update_draft_story` always leave `status` as `draft`. Uploading an image does not attach it and does not publish. `publish_story` is the only tool that sets `published`. Calling it again does not rewrite the body, gallery, hero, or alt text. Updates refuse a story that is already published.

## Grok Bot

Grok Bot's documented remote connector is HTTPS Streamable HTTP. A pre-issued bearer token is a documented connector header (`Authorization: Bearer ...`). In the bot chat, add a custom remote MCP server:

- Name: `photography-cms`
- URL: `https://donaldsextonphotography.com/mcp/cms`
- Header name: `Authorization`
- Header value: `Bearer dsp_mcp_...`

The token is a bot secret. Do not put it in the URL.

Interactive OAuth 2.1 with dynamic client registration is also described for Grok Bot. This app does not install Laravel Passport, does not publish OAuth metadata, and does not implement that connect-card flow. Whether a Grok Bot build that ignores a configured Authorization header and demands OAuth will connect is unverified. Do not assume that path works.

If the connector sends an `Origin` header, the host must match `APP_URL`. A request with no `Origin`, which is the normal server-side connector, is accepted. A browser origin for another host is rejected.

## Uploads

Send image bytes as base64. The server does not fetch URLs, so there is no SSRF fetch path. Decoded size must be from 1 byte through 4,000,000 bytes. The detected type must be JPEG, PNG, WebP, or GIF, and the bytes must decode as an image. Reuse the same `idempotency_key` to retry. The same key and same bytes return the original media row. The same key with different bytes is rejected. A different key stores a new row.

## Hosting

No new paid service is required. The route is part of this Laravel app. Production already deploys with GitHub Actions over SSH after CI succeeds on `main`, as described in `docs/deployment/hetzner-cpx11-deploy.md`. This change does not run that deploy and does not read or change production secrets. After a later approved deploy, issue the token on the server with `php artisan cms-mcp:issue`. The public URL is HTTPS on the existing site. Local `stdio` and `localhost` are not how Grok Bot reaches a connector.

## Review before use

- Confirm the Grok Bot build you have accepts a bearer header on a Streamable HTTP POST endpoint that answers JSON rather than holding an SSE stream.
- Confirm it does not require the OAuth connect card. That flow is not implemented.
- Issue a credential without `stories:publish` until a person has reviewed a draft.
- Revoke a token that was shown in a chat transcript.
