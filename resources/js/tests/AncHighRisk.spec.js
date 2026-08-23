import { describe, it, expect, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import AncVisitForm from '@/Pages/Domains/Clinical/AncVisitForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initialData) => reactive({
        ...initialData,
        post: vi.fn(),
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
}));

describe('Antenatal Care (ANC) & High-Risk Obstetric Evaluation in AncVisitForm.vue', () => {
    it('calculates EDD using Naegele rule (+280 days) upon LMP input', async () => {
        const wrapper = mount(AncVisitForm, {
            props: {
                encounterId: 'enc-anc-1',
                existingVisits: [],
            },
        });

        // Set LMP to 2026-01-01
        wrapper.vm.form.last_menstrual_period = '2026-01-01';
        wrapper.vm.onLmpChange();

        // 2026-01-01 + 280 days = 2026-10-08
        expect(wrapper.vm.form.estimated_date_of_delivery).toBe('2026-10-08');
    });

    it('displays high-risk input fields when high_risk_flag is toggled', async () => {
        const wrapper = mount(AncVisitForm, {
            props: {
                encounterId: 'enc-anc-1',
                existingVisits: [],
            },
        });

        expect(wrapper.text()).toContain('Flag as High-Risk Pregnancy');
        expect(wrapper.find('input[placeholder*="State risk reason"]').exists()).toBe(false);

        wrapper.vm.form.high_risk_flag = true;
        wrapper.vm.form.high_risk_reason = 'Severe pre-eclampsia with proteinuria';
        await wrapper.vm.$nextTick();

        expect(wrapper.find('input[placeholder*="State risk reason"]').exists()).toBe(true);
    });
});
