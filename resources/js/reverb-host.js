export function shouldUseCurrentReverbHost(configuredHost, currentHost) {
    return currentHost.endsWith('.trycloudflare.com')
        || currentHost === 'localhost'
        || currentHost === '127.0.0.1'
        || !configuredHost
        || configuredHost.includes('trycloudflare.com')
        || configuredHost === 'localhost'
        || configuredHost === '127.0.0.1';
}
