import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PartographForm from '@/Pages/Domains/Clinical/PartographForm.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initialData) => ({
        ...initialData,
        post: vi.fn(),
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
}));

describe('WHO Intrapartum Partograph SFC in PartographForm.vue', () => {
    it('renders labor progress fields and default clinical indicators', () => {
        const wrapper = mount(PartographForm, {
            props: {
                encounterId: 'enc-labour-1',
                existingEntries: [],
            },
        });

        expect(wrapper.text()).toContain('WHO Intrapartum Partograph Surveillance');
        expect(wrapper.text()).toContain('Cervical Dilation (cm)');
        expect(wrapper.text()).toContain('Fetal Heart Rate (bpm)');
        expect(wrapper.vm.form.cervical_dilation_cm).toBe(4);
    });

    it('toggles emergency action line monitoring when action_line_crossed is checked', async () => {
        const wrapper = mount(PartographForm, {
            props: {
                encounterId: 'enc-labour-1',
                existingEntries: [],
            },
        });

        expect(wrapper.vm.form.action_line_crossed).toBe(false);

        wrapper.vm.form.action_line_crossed = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.form.action_line_crossed).toBe(true);
        expect(wrapper.text()).toContain('Action Line Crossed (EMOC)');
    });
});
