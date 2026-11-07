import assert from 'node:assert/strict';
import test from 'node:test';

import { shouldUseCurrentReverbHost } from '../resources/js/reverb-host.js';

test('Cloudflare tunnel pages use their current host even with a production Reverb host', () => {
    assert.equal(
        shouldUseCurrentReverbHost('rk.whusnet.com', 'distinct-consortium-suited-dome.trycloudflare.com'),
        true,
    );
});

test('local pages use their current host even with a production Reverb host', () => {
    assert.equal(shouldUseCurrentReverbHost('rk.whusnet.com', 'localhost'), true);
    assert.equal(shouldUseCurrentReverbHost('rk.whusnet.com', '127.0.0.1'), true);
});

test('production pages can use a dedicated Reverb host', () => {
    assert.equal(shouldUseCurrentReverbHost('realtime.whusnet.com', 'rk.whusnet.com'), false);
});

