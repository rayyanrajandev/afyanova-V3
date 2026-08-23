import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Register from '@/Pages/Domains/Patient/Register.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Registrar User' } },
            flash: {},
        },
    }),
    useForm: (initialData) => ({
        ...initialData,
        post: vi.fn(),
        processing: false,
        errors: {},
        reset: vi.fn(),
        clearErrors: vi.fn(),
    }),
    router: {
        post: vi.fn(),
    },
    Head: { template: '<title><slot /></title>' },
    Link: { template: '<a><slot /></a>' },
}));

describe('Patient Registration Privacy & PII Protection in Register.vue', () => {
    beforeEach(() => {
        sessionStorage.clear();
        vi.clearAllMocks();
    });

    it('excludes plaintext National ID / NIDA from autosaved draft session storage', async () => {
        const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

        const wrapper = mount(Register, {
            props: {
                initialQuery: '',
                facilities: [{ id: 'fac-1', name: 'Main Hospital' }],
            },
        });

        // Fill form fields including sensitive national ID
        wrapper.vm.form.first_name = 'Juma';
        wrapper.vm.form.last_name = 'Mkwawa';
        wrapper.vm.form.phone = '0755123456';
        wrapper.vm.form.national_id = '19900101-12345-00001-20'; // Sensitive PII

        // Trigger autosave
        wrapper.vm.saveDraftToSession();

        expect(setItemSpy).toHaveBeenCalled();
        const savedJson = sessionStorage.getItem('afyanova_patient_reg_draft_v3');
        expect(savedJson).not.toBeNull();

        const parsed = JSON.parse(savedJson);
        expect(parsed.first_name).toBe('Juma');
        expect(parsed.last_name).toBe('Mkwawa');
        expect(parsed.national_id).toBeUndefined(); // Crucial: NIDA must be stripped from local draft storage
    });
});
