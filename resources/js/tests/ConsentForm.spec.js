import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ConsentForm from '@/Pages/Domains/Clinical/ConsentForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initialData) => ({
        ...initialData,
        post: vi.fn(),
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
}));

describe('Informed Consent Capture SFC in ConsentForm.vue', () => {
    it('renders statutory consent options and pre-populates default Surgical type', () => {
        const wrapper = mount(ConsentForm, {
            props: {
                encounterId: 'enc-101',
                existingConsents: [],
            },
        });

        expect(wrapper.text()).toContain('Statutory Informed Consent & Procedure Authorization');
        expect(wrapper.text()).toContain('Consent Type *');
        expect(wrapper.vm.form.consent_type).toBe('Surgical');
    });

    it('submits form to clinical.consent.store with encounterId', async () => {
        const wrapper = mount(ConsentForm, {
            props: {
                encounterId: 'enc-999',
                existingConsents: [],
            },
        });

        wrapper.vm.form.procedure_title = 'Emergency Appendectomy';
        wrapper.vm.form.explanation_of_risks = 'Hemorrhage, infection, anesthetic risk';
        wrapper.vm.form.signatory_name = 'Patient Mary Doe';

        await wrapper.vm.submit();

        expect(wrapper.vm.form.post).toHaveBeenCalledWith('/clinical.consent.store/"enc-999"', expect.any(Object));
    });
});
