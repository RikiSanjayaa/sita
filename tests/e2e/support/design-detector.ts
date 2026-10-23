import { spawn } from 'node:child_process';
import net from 'node:net';
import path from 'node:path';

import { chromium } from '@playwright/test';

import { repositoryRoot } from './db';

export type DetectorFinding = {
    antipattern: string;
    name?: string;
    severity?: string;
    category?: string;
    selector?: string;
    snippet?: string;
    advisory?: boolean;
};

export type DetectorScan = {
    /** Findings that count as failures (everything except advisories). */
    blocking: DetectorFinding[];
    advisory: DetectorFinding[];
    all: DetectorFinding[];
    /** Occurrences per rule id, blocking and advisory together. */
    counts: Record<string, number>;
};

const launcher = path.join(
    repositoryRoot,
    '.claude',
    'skills',
    'impeccable',
    'scripts',
    'impeccable',
);

export async function findFreePort(): Promise<number> {
    const { promise, resolve, reject } = Promise.withResolvers<number>();
    const server = net.createServer();

    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
        const address = server.address();

        server.close(() => {
            if (address && typeof address === 'object') {
                resolve(address.port);
            } else {
                reject(new Error('Could not reserve a free TCP port.'));
            }
        });
    });

    return promise;
}

export function countByRule(
    findings: DetectorFinding[],
): Record<string, number> {
    const counts: Record<string, number> = {};

    for (const finding of findings) {
        counts[finding.antipattern] = (counts[finding.antipattern] ?? 0) + 1;
    }

    return counts;
}

export function formatCounts(counts: Record<string, number>): string {
    const entries = Object.entries(counts).sort(([a], [b]) =>
        a.localeCompare(b),
    );

    return entries.length === 0
        ? 'no findings'
        : entries.map(([rule, count]) => `${rule} x${count}`).join(', ');
}

/**
 * Runs `impeccable detect --json --cdp <port> --target <target>` against a page
 * that is already open in a Chromium started with `--remote-debugging-port`.
 * The detector reads the page as shown and never navigates it.
 *
 * Exit status 0 (clean) and 2 (findings) are both successful scans. Anything
 * else, or output that is not a JSON array, means the scan could not run and
 * throws.
 */
export async function runDetector(options: {
    port: number;
    target: string;
}): Promise<DetectorScan> {
    const args = [
        'detect',
        '--json',
        '--cdp',
        String(options.port),
        '--target',
        options.target,
    ];

    const { promise, resolve, reject } = Promise.withResolvers<{
        code: number | null;
        stdout: string;
        stderr: string;
    }>();
    const child = spawn(launcher, args, {
        cwd: repositoryRoot,
        env: {
            ...process.env,
            IMPECCABLE_BROWSER:
                process.env.IMPECCABLE_BROWSER ??
                process.env.PLAYWRIGHT_EXECUTABLE_PATH ??
                chromium.executablePath(),
        },
    });
    let stdout = '';
    let stderr = '';

    child.stdout.on('data', (chunk: Buffer) => {
        stdout += chunk.toString();
    });
    child.stderr.on('data', (chunk: Buffer) => {
        stderr += chunk.toString();
    });
    child.once('error', reject);
    child.once('close', (code) => resolve({ code, stdout, stderr }));

    const result = await promise;

    if (result.code !== 0 && result.code !== 2) {
        throw new Error(
            `impeccable detect could not scan ${options.target} (exit ${String(result.code)}): ${result.stderr.trim() || result.stdout.trim()}`,
        );
    }

    let parsed: unknown;

    try {
        parsed = JSON.parse(result.stdout);
    } catch {
        throw new Error(
            `impeccable detect printed no JSON for ${options.target}: ${result.stdout.slice(0, 500)}`,
        );
    }

    if (!Array.isArray(parsed)) {
        throw new Error(
            `impeccable detect printed unexpected JSON for ${options.target}: ${result.stdout.slice(0, 500)}`,
        );
    }

    const all = parsed as DetectorFinding[];

    return {
        all,
        blocking: all.filter((finding) => finding.advisory !== true),
        advisory: all.filter((finding) => finding.advisory === true),
        counts: countByRule(all),
    };
}
