import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import { router } from '@inertiajs/vue3';

let mockPageProps = {};

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: mockPageProps,
    }),
    router: {
        delete: vi.fn(),
        post: vi.fn(),
        get: vi.fn(),
    },
    Link: {
        template: '<a><slot /></a>',
    },
}));

describe('Break-Glass Banner in AfyaShell.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPageProps = {
            auth: { user: { name: 'Dr. Test' } },
            flash: {},
            breakGlass: null,
        };
    });

    it('does not render break-glass banner when breakGlass prop is not set', () => {
        const wrapper = mount(AfyaShell, {
            props: { activeModule: 'clinical' },
            slots: { default: '<div>Workspace content</div>' },
        });

        expect(wrapper.text()).not.toContain('BREAK-GLASS EMERGENCY OVERRIDE ACTIVE');
    });

    it('renders break-glass banner with active timer when breakGlass is active in session', async () => {
        mockPageProps = {
            auth: { user: { name: 'Dr. Test' } },
            flash: {},
            breakGlass: {
                active: true,
                expiresAt: Math.floor(Date.now() / 1000) + 900, // 15 mins left
                facilityId: 'fac-123',
            },
        };

        const wrapper = mount(AfyaShell, {
            props: { activeModule: 'clinical' },
            slots: { default: '<div>Workspace content</div>' },
        });

        expect(wrapper.text()).toContain('BREAK-GLASS EMERGENCY OVERRIDE ACTIVE');
        expect(wrapper.text()).toContain('Revoke Override');
    });

    it('triggers router.delete when clicking Revoke Override button', async () => {
        mockPageProps = {
            auth: { user: { name: 'Dr. Test' } },
            flash: {},
            breakGlass: {
                active: true,
                expiresAt: Math.floor(Date.now() / 1000) + 900,
                facilityId: 'fac-123',
            },
        };

        const wrapper = mount(AfyaShell, {
            props: { activeModule: 'clinical' },
            slots: { default: '<div>Workspace content</div>' },
        });

        const revokeBtn = wrapper.findAll('button').find(b => b.text().includes('Revoke Override'));
        expect(revokeBtn).toBeDefined();
        await revokeBtn.trigger('click');

        expect(router.delete).toHaveBeenCalledWith('/clinical.break-glass.destroy', expect.any(Object));
    });
});
