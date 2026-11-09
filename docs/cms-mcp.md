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

Grok Bot's `AddMcpServer` schema accepts a remote URL and a `headers` object. A static bearer token fits that schema:

- Name: `photography-cms`
- URL: `https://donaldsextonphotography.com/mcp/cms`
- Header name: `Authorization`
- Header value: `Bearer dsp_mcp_...`

That is a supported configuration shape. It is not a tested end-to-end connection from Grok Bot to this application. This repository has not added the server to a Grok Bot account, has not opened a tunnel, and has not deployed `/mcp/cms`. OAuth is optional in that schema and is not a deployment blocker. This app does not implement the OAuth connect-card flow.

The protocol test that does run here uses the official `@modelcontextprotocol/sdk` Streamable HTTP client against `php artisan serve` on `127.0.0.1`, with a disposable sqlite file and a disposable credential. That client sends `initialize`, `notifications/initialized`, `tools/list`, and `tools/call`, including `Accept: application/json, text/event-stream` and `Content-Type: application/json`. It is not Grok Bot.

If the connector sends an `Origin` header, the host must match `APP_URL`. A request with no `Origin` is accepted. A browser origin for another host is rejected.

`tools/list` returns only the tools the credential's abilities allow. A direct call to a hidden tool does not write data.

Initialize returns `MCP-Session-Id`. The server does not store that session, so the header is not an access boundary and later calls work without it. The bearer token is the access boundary. `GET` and `DELETE` answer `405`; there is no SSE listen channel and no session-termination method.

## Uploads

Send image bytes as base64. The server does not fetch URLs, so there is no SSRF fetch path. Decoded size must be from 1 byte through 4,000,000 bytes. The detected type must be JPEG, PNG, WebP, or GIF, and the bytes must decode as an image. Reuse the same `idempotency_key` to retry. The same key and same bytes return the original media row. The same key with different bytes is rejected. A different key stores a new row.

## Hosting

No new paid service is required. The route is part of this Laravel app. Production already deploys with GitHub Actions over SSH after CI succeeds on `main`, as described in `docs/deployment/hetzner-cpx11-deploy.md`. This change does not run that deploy and does not read or change production secrets. After a later approved deploy, issue the token on the server with `php artisan cms-mcp:issue`. The public URL is HTTPS on the existing site. Local `stdio` and `localhost` are not how Grok Bot reaches a connector.

## Review before use

- A Grok Bot account connection is still a manual check after a later approved deploy. The static `headers` field is supported by the AddMcpServer schema; that live connection has not been run.
- Issue a credential without `stories:publish` until a person has reviewed a draft.
- Revoke a token that was shown in a chat transcript.
- Do not run the public host with `APP_DEBUG=true`. This application does not log the bearer token, but a debug error page can include request headers.
- Uploads use standard base64, not a URL. Two retries with the same key are serialized by a unique `(credential, idempotency key)` row. The losing write rolls back its media row and deletes the file it stored.
