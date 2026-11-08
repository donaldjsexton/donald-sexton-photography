import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const token = process.env.MCP_TOKEN ?? '';
const url = process.env.MCP_URL ?? '';
const slug = process.env.MCP_SLUG ?? '';
const png = process.env.MCP_PNG ?? '';

function redact(value) {
    const text = typeof value === 'string' ? value : JSON.stringify(value);
    return token === '' ? text : text.split(token).join('[redacted]');
}

if (token === '' || url === '' || slug === '' || png === '') {
    console.error('missing protocol test configuration');
    process.exit(2);
}

const transport = new StreamableHTTPClientTransport(new URL(url), {
    requestInit: {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    },
});
const client = new Client({
    name: 'cms-mcp-protocol-test',
    version: '1.0.0',
});

try {
    await client.connect(transport);
    const listed = await client.listTools();
    const lookedUp = await client.callTool({
        name: 'lookup_story',
        arguments: { slug },
    });
    const [firstUpload, secondUpload] = await Promise.all([
        client.callTool({
            name: 'upload_media',
            arguments: {
                content_base64: png,
                idempotency_key: 'protocol-retry-001',
                alt_text: 'Protocol shoreline alt',
            },
        }),
        client.callTool({
            name: 'upload_media',
            arguments: {
                content_base64: png,
                idempotency_key: 'protocol-retry-001',
                alt_text: 'Protocol shoreline alt',
            },
        }),
    ]);
    let publishRejected = false;
    try {
        const publish = await client.callTool({
            name: 'publish_story',
            arguments: { story_id: lookedUp.structuredContent.story.id },
        });
        publishRejected = publish.isError === true;
    } catch {
        publishRejected = true;
    }
    const uploadIds = [firstUpload, secondUpload].map((result) => result.structuredContent?.media?.id ?? null);
    process.stdout.write(JSON.stringify({
        toolNames: listed.tools.map((tool) => tool.name).sort(),
        sessionIdPresent: Boolean(transport.sessionId),
        lookupIsError: lookedUp.isError === true,
        body: lookedUp.structuredContent?.story?.body ?? null,
        status: lookedUp.structuredContent?.story?.status ?? null,
        uploadIds,
        uploadErrors: [firstUpload.isError === true, secondUpload.isError === true],
        publishRejected,
    }));
} catch (error) {
    console.error(redact(error instanceof Error ? error.message : error));
    process.exit(1);
} finally {
    await client.close();
}
