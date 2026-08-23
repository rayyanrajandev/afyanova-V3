import { config } from '@vue/test-utils';

const routeMock = (name, params) => {
    if (params) {
        return `/${name}/${JSON.stringify(params)}`;
    }
    return `/${name}`;
};

global.route = routeMock;

config.global.mocks = {
    route: routeMock,
};

// Window matchMedia mock for JSDOM
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false,
    }),
});

