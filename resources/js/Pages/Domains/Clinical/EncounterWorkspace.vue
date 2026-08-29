<script setup>
import { defineProps, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VitalsForm from './VitalsForm.vue';
import SOAPNote from './SOAPNote.vue';
import PrescriptionPad from '../Pharmacy/PrescriptionPad.vue';

const props = defineProps({
    encounter: Object,
    formularies: Array,
});

const activeTab = ref('vitals');
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Clinical Workspace: {{ encounter.patient.first_name }} {{ encounter.patient.last_name }}
                </h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">Visit: {{ encounter.status }}</span>
                    <button class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700">
                        Sign & Close Visit
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-12 gap-6">
                
                <!-- Left Sidebar: Patient History -->
                <div class="col-span-12 md:col-span-3 space-y-6">
                    <div class="bg-white shadow-sm overflow-hidden sm:rounded-lg p-4">
                        <h3 class="text-md font-medium text-gray-900 border-b pb-2 mb-2">Patient Summary</h3>
                        <p class="text-sm text-gray-600">MRN: {{ encounter.patient.primary_mrn }}</p>
                        <p class="text-sm text-gray-600">Gender: {{ encounter.patient.gender }}</p>
                        <p class="text-sm text-gray-600">DOB: {{ encounter.patient.dob || 'Unknown' }}</p>
                    </div>

                    <div class="bg-white shadow-sm overflow-hidden sm:rounded-lg p-4">
                        <h3 class="text-md font-medium text-gray-900 border-b pb-2 mb-2">Active Allergies</h3>
                        <ul v-if="encounter.patient.allergies.length > 0" class="text-sm space-y-1">
                            <li v-for="allergy in encounter.patient.allergies" :key="allergy.id" class="text-red-600 font-medium">
                                {{ allergy.allergen }} ({{ allergy.severity }})
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-500">No known allergies.</p>
                    </div>
                </div>

                <!-- Right Workspace: Charting -->
                <div class="col-span-12 md:col-span-9">
                    <div class="bg-white shadow-sm overflow-hidden sm:rounded-lg">
                        
                        <!-- Tabs -->
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex" aria-label="Tabs">
                                <button @click="activeTab = 'vitals'" :class="[activeTab === 'vitals' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm']">
                                    Vitals & Triage
                                </button>
                                <button @click="activeTab = 'soap'" :class="[activeTab === 'soap' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm']">
                                    SOAP Notes
                                </button>
                                <button @click="activeTab = 'rx'" :class="[activeTab === 'rx' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm']">
                                    Prescriptions
                                </button>
                            </nav>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <div v-if="activeTab === 'vitals'">
                                <VitalsForm :encounter-id="encounter.id" :existing-vitals="encounter.vitals" />
                            </div>
                            
                            <div v-if="activeTab === 'soap'">
                                <SOAPNote :encounter-id="encounter.id" :existing-notes="encounter.notes" :existing-diagnoses="encounter.diagnoses" />
                            </div>

                            <div v-if="activeTab === 'rx'">
                                <PrescriptionPad :encounter-id="encounter.id" :formularies="formularies || []" />
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
