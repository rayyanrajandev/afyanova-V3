import { config } from '@vue/test-utils';

// Global mocks for Ziggy route() and window properties
global.route = (name, params) => {
    if (params) {
        return `/${name}/${JSON.stringify(params)}`;
    }
    return `/${name}`;
};

