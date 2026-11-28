import { createInertiaApp, router } from '@inertiajs/svelte';

const basePath = (
    document.querySelector('meta[name="app-base-path"]')?.getAttribute('content') || ''
).replace(/\/+$/, '');

function withBasePath(url) {
    if (!basePath || typeof url !== 'string') {
        return url;
    }
    if (url.startsWith('/') && !url.startsWith('//') && url !== basePath && !url.startsWith(`${basePath}/`)) {
        return `${basePath}${url}`;
    }
    return url;
}

if (basePath) {
    router.on('before', (event) => {
        const visitUrl = event.detail?.visit?.url;
        if (visitUrl && visitUrl.origin === window.location.origin) {
            const nextPath = withBasePath(visitUrl.pathname);
            if (nextPath !== visitUrl.pathname) {
                visitUrl.pathname = nextPath;
            }
        }
    });

    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, init) => {
        if (typeof input === 'string') {
            return originalFetch(withBasePath(input), init);
        }
        if (input instanceof Request && input.url.startsWith(window.location.origin)) {
            const parsed = new URL(input.url);
            const nextPath = withBasePath(parsed.pathname);
            if (nextPath !== parsed.pathname) {
                parsed.pathname = nextPath;
                return originalFetch(new Request(parsed.toString(), input), init);
            }
        }
        return originalFetch(input, init);
    };

    const originalXhrOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        return originalXhrOpen.call(this, method, withBasePath(url), ...rest);
    };

    const rewriteMediaElements = (root) => {
        if (!root || !root.querySelectorAll) {
            return;
        }
        root.querySelectorAll('img[src^="/"], video[src^="/"], audio[src^="/"], iframe[src^="/"], a[href^="/"]').forEach((el) => {
            const attr = el.tagName === 'A' ? 'href' : 'src';
            const val = el.getAttribute(attr);
            const next = withBasePath(val);
            if (next && next !== val) {
                el.setAttribute(attr, next);
            }
        });
    };

    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType === 1) {
                    if (node.matches?.('img[src^="/"], video[src^="/"], audio[src^="/"], iframe[src^="/"], a[href^="/"]')) {
                        const attr = node.tagName === 'A' ? 'href' : 'src';
                        const val = node.getAttribute(attr);
                        const next = withBasePath(val);
                        if (next && next !== val) {
                            node.setAttribute(attr, next);
                        }
                    }
                    rewriteMediaElements(node);
                }
            }
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', () => rewriteMediaElements(document));
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
        return pages[`./Pages/${name}.svelte`];
    },
});
