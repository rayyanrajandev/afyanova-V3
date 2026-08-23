<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { 
    Users, 
    UserPlus, 
    Search as SearchIcon, 
    Clock, 
    Info, 
    ShieldCheck, 
    ArrowLeft,
    Calendar,
    Sparkles,
    Check,
    MapPin,
    Building2,
    Activity,
    Syringe,
    FileText,
    History,
    X,
    Loader2
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import InputError from '@/Components/InputError.vue';

const DRAFT_STORAGE_KEY = 'afyanova_patient_reg_draft_v3';
const restoredDraftNotice = ref(false);
const activeSection = ref('demographics'); // 'demographics' | 'dob_age' | 'address' | 'contacts'

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    dob: '',
    gender: 'Female',
    blood_group: '',
    marital_status: '',
    occupation: '',
    nationality: 'Tanzanian',
    phone: '',
    email: '',
    nida: '',
    region: 'Dar es Salaam',
    district: 'Ilala',
    ward: 'Kariakoo',
    street_address: '',
});

// Comprehensive Tanzanian Administrative Structure (Region -> District -> Ward)
const tanzanianAdministrativeData = {
    'Dar es Salaam': {
        districts: {
            'Ilala': ['Kariakoo', 'Upanga Magharibi', 'Upanga Mashariki', 'Gerezani', 'Kisutu', 'Kivukoni', 'Mchafukoge', 'Ilala', 'Buguruni', 'Vingunguti', 'Tabata', 'Segerea', 'Kipawa', 'Ukonga', 'Kitunda', 'Chanika', 'Gongo la Mboto'],
            'Kinondoni': ['Sinza', 'Mikocheni', 'Msasani', 'Kijitonyama', 'Mwananyamala', 'Hananasif', 'Kinondoni', 'Magomeni', 'Ndugumbi', 'Tandale', 'Kigogo', 'Kawe', 'Kunduchi', 'Mbezi Beach', 'Wazo'],
            'Ubungo': ['Ubungo', 'Mburahati', 'Manzese', 'Mabibo', 'Kimara', 'Mbezi', 'Kibamba', 'Goba', 'Saranga', 'Makuburi', 'Kwangulelo'],
            'Temeke': ['Temeke', 'Mbagala', 'Mbagala Kuu', 'Kurasini', 'Chang\'ombe', 'Tandika', 'Keko', 'Yombo Vituka', 'Sandali', 'Toangoma', 'Chamazi', 'Kimbiji'],
            'Kigamboni': ['Kigamboni', 'Kimbiji', 'Mjimwema', 'Pembamnazi', 'Somangila', 'Tungi', 'Vijibweni'],
        }
    },
    'Arusha': {
        districts: {
            'Arusha City': ['Sekei', 'Kaloleni', 'Themi', 'Kati', 'Levolosi', 'Ngarenaro', 'Unga Ltd', 'Sombetini', 'Sokon I', 'Kimandolu', 'Lemara', 'Oloirien', 'Terrat', 'Elerai'],
            'Arusha District (Rural)': ['Bangata', 'Bwawani', 'Ilkiding\'a', 'Kiranyi', 'Kisongo', 'Mwandeti', 'Nkoaranga', 'Oldonyosambu', 'Oltrumet'],
            'Karatu': ['Karatu', 'Endabash', 'Ganako', 'Rhotia', 'Qurus', 'Oldeani', 'Mbulumbulu'],
            'Monduli': ['Monduli Mjini', 'Engaruka', 'Lolkisale', 'Mto wa Mbu', 'Sepeko', 'Selela'],
            'Ngorongoro': ['Ngorongoro', 'Endulen', 'Nainokanoka', 'Loliondo', 'Sale', 'Digodigo'],
            'Meru': ['Usa River', 'Majengo', 'King\'ori', 'Leguruki', 'Poli', 'Songoro', 'Nkoaranga'],
            'Longido': ['Longido', 'Namanga', 'Engarenaibor', 'Kamwanga', 'Matale'],
        }
    },
    'Dodoma': {
        districts: {
            'Dodoma City': ['Kikuyu Kaskazini', 'Kikuyu Kusini', 'Tambukareli', 'Majengo', 'Kizota', 'Ipagala', 'Chamwino', 'Nzuguni', 'Makole', 'Hazina', 'Mnadani', 'Hombolo', 'Msalato'],
            'Bahi': ['Bahi', 'Chipanga', 'Ibihwa', 'Ilindi', 'Kigwe', 'Lamaiti', 'Mundemu'],
            'Chamwino': ['Buigiri', 'Chamwino', 'Chilonwa', 'Handali', 'Ikowa', 'Itiso', 'Mlowa Bwawani', 'Msanga'],
            'Chemba': ['Chemba', 'Farkwa', 'Goima', 'Kinyasi', 'Mondo', 'Songolo'],
            'Kondoa': ['Kondoa Mjini', 'Bumbuta', 'Haubi', 'Kikore', 'Kolo', 'Pahi', 'Soera'],
            'Kongwa': ['Kongwa', 'Chamkoroma', 'Hogoro', 'Kibaigwa', 'Mlali', 'Pandambili', 'Ugogoni'],
            'Mpwapwa': ['Mpwapwa Mjini', 'Chunyu', 'Gulwe', 'Kibakwe', 'Massa', 'Matomondo', 'Rudi', 'Wotta'],
        }
    },
    'Mwanza': {
        districts: {
            'Nyamagana': ['Nyamagana', 'Mirongo', 'Isamilo', 'Pamba', 'Mabatini', 'Igoma', 'Mahina', 'Buhongwa', 'Mkolani', 'Butimba', 'Nyegezi', 'Luchelele'],
            'Ilemela': ['Ilemela', 'Kirumba', 'Kitangiri', 'Pasiansi', 'Bugogwa', 'Buswelu', 'Kahama', 'Nyamanoro', 'Sangabuye'],
            'Magu': ['Magu Mjini', 'Kahangara', 'Kisesa', 'Kitongo', 'Luhumbo', 'Nyanguge', 'Shishani'],
            'Misungwi': ['Misungwi', 'Fella', 'Gulumungu', 'Igokelo', 'Koromije', 'Usagara'],
            'Kwimba': ['Ngudu Mjini', 'Bungulwa', 'Hungumalwa', 'Malya', 'Nyambiti', 'Sumve'],
            'Sengerema': ['Sengerema Mjini', 'Busisi', 'Buyagu', 'Chifunfu', 'Kagunga', 'Katunguru', 'Tabaruka'],
            'Ukerewe': ['Nansio Mjini', 'Bukindo', 'Bwiro', 'Kagera', 'Muriti', 'Namagondo'],
        }
    },
    'Kilimanjaro': {
        districts: {
            'Moshi Municipal': ['Kiusa', 'Korongoni', 'Mawenzi', 'Bondeni', 'Kiboriloni', 'Majengo', 'Pasua', 'Rau', 'Shanty Town', 'Soweto'],
            'Moshi Rural': ['Kibosho Kati', 'Kibosho Magharibi', 'Kibosho Mashariki', 'Kirua Vunjo', 'Marangu Magharibi', 'Marangu Mashariki', 'Uru Kaskazini', 'Uru Kusini'],
            'Hai': ['Hai Mjini', 'Machame Kaskazini', 'Machame Kusini', 'Machame Magharibi', 'Masama', 'Weruweru'],
            'Siha': ['Siha Mjini', 'Donyomuriet', 'Gararagua', 'Ivaeny', 'Nasai', 'Sanya Juu'],
            'Rombo': ['Mkuu Mjini', 'Useri', 'Tarakea Motamburu', 'Kelamfua Mokala', 'Mashati', 'Mengwe'],
            'Same': ['Same Mjini', 'Hedaru', 'Makanya', 'Mwembe', 'Ndungu', 'Ruvu', 'Vudee'],
            'Mwanga': ['Mwanga Mjini', 'Kighare', 'Kigogo', 'Lembeni', 'Shighatini', 'Usangi'],
        }
    },
    'Mbeya': {
        districts: {
            'Mbeya City': ['Mbeya Mjini', 'Forest', 'Iyela', 'Ilomba', 'Mwanjelwa', 'Sisimba', 'Uyole', 'Ruanda', 'Nonde', 'Nzovwe', 'Iwambi', 'Itende', 'Ghana'],
            'Mbeya District': ['Inyala', 'Isuto', 'Ilembo', 'Mshewe', 'Santilya', 'Tembela', 'Utengule Usangu'],
            'Rungwe': ['Tukuyu Mjini', 'Bulyaga', 'Ibaba', 'Ikuti', 'Kandete', 'Kyimo', 'Lufingo', 'Mwakaleli'],
            'Kyela': ['Kyela Mjini', 'Ipinda', 'Katumba Songwe', 'Matema', 'Ngana', 'Ngonga'],
            'Chunya': ['Chunya Mjini', 'Chalangwa', 'Lupa Tingatinga', 'Makongolosi', 'Matundasi', 'Sangambi'],
            'Mbarali': ['Rujewa', 'Chimala', 'Igurusi', 'Madibira', 'Mahongole', 'Mapogoro', 'Ubaruku'],
        }
    },
    'Morogoro': {
        districts: {
            'Morogoro Municipal': ['Boma', 'Kichangani', 'Kilakala', 'Kingolwira', 'Mazimbu', 'Mbuyuni', 'Mji Mkuu', 'Mwembesongo', 'Sabasaba', 'Sultan Area'],
            'Morogoro Rural': ['Bungu', 'Duthumi', 'Kibogwa', 'Kiroka', 'Kisaki', 'Matombo', 'Mngazi', 'Ngerengere'],
            'Kilosa': ['Kilosa Mjini', 'Kimamba', 'Mikumi', 'Rudewa', 'Tindiga', 'Ulaya'],
            'Ifakara (Kilombero)': ['Ifakara Mjini', 'Kibaoni', 'Kidatu', 'Mang\'ula', 'Mbingu', 'Mlimba', 'Mngeta'],
            'Gairo': ['Gairo Mjini', 'Chagongwe', 'Iyogwe', 'Kibaigwa', 'Rubeho'],
            'Mvomero': ['Mvomero', 'Dakawa', 'Hembeti', 'Kikeo', 'Mlali', 'Turiani'],
            'Ulanga': ['Mahenge Mjini', 'Chilombola', 'Iragua', 'Lupiro', 'Minepa'],
        }
    },
    'Tanga': {
        districts: {
            'Tanga City': ['Central', 'Chumbageni', 'Majengo', 'Makorora', 'Ngamiani Kaskazini', 'Ngamiani Kati', 'Ngamiani Kusini', 'Pongwe', 'Raskazone', 'Usagara'],
            'Muheza': ['Muheza Mjini', 'Amani', 'Magila', 'Ngomeni', 'Tongwe'],
            'Korogwe': ['Korogwe Mjini', 'Boma', 'Kizara', 'Lwengera', 'Magoma', 'Mombo', 'Old Korogwe'],
            'Lushoto': ['Lushoto Mjini', 'Gare', 'Kwesimu', 'Malibwi', 'Mlalo', 'Rangwi', 'Soni'],
            'Pangani': ['Pangani Mjini', 'Bweni', 'Kipumbwi', 'Madanga', 'Mwera', 'Ushongo'],
            'Handeni': ['Handeni Mjini', 'Chanika', 'Kiva', 'Mkata', 'Mziha', 'Sindeni'],
            'Kilindi': ['Songe', 'Kikunde', 'Kwediboma', 'Masagalu', 'Negero'],
            'Mkinga': ['Maramba', 'Duga', 'Moa', 'Mtimbwani', 'Sigaya'],
        }
    },
    'Pwani': {
        districts: {
            'Kibaha Town': ['Kibaha Mjini', 'Maili Moja', 'Kongowe', 'Tumbi', 'Visiga', 'Picha ya Ndege'],
            'Kibaha Rural': ['Mlandizi', 'Ruvu', 'Soga', 'Kikongo', 'Magindu'],
            'Bagamoyo': ['Bagamoyo Mjini', 'Dunda', 'Kaole', 'Kiromo', 'Magomeni', 'Yombo', 'Zinga'],
            'Chalinze': ['Chalinze Mjini', 'Bwilingu', 'Kigaro', 'Lugoba', 'Mbwewe', 'Mkange', 'Talawanda'],
            'Kisarawe': ['Kisarawe Mjini', 'Kazimzumbwi', 'Maneromango', 'Marui', 'Minaki', 'Mzenga'],
            'Mkuranga': ['Mkuranga Mjini', 'Bupu', 'Kisiju', 'Magawa', 'Mbezi', 'Nyamato', 'Shungubweni'],
            'Rufiji (Utete)': ['Utete Mjini', 'Bungu', 'Ikwiriri', 'Kibiti', 'Mbwara', 'Mohoro'],
            'Mafia Island': ['Kilindoni', 'Baleni', 'Jibondo', 'Kanga', 'Kiegeani', 'Kirongwe'],
        }
    },
    'Tabora': {
        districts: {
            'Tabora Municipal': ['Chemchem', 'Gongoni', 'Ipuli', 'Isevya', 'Kitete', 'Malolo', 'Mbugani', 'Ng\'ambo', 'Tambukareli', 'Uyui'],
            'Nzega': ['Nzega Mjini', 'Itobo', 'Lusu', 'Mambali', 'Ndala', 'Puge'],
            'Igunga': ['Igunga Mjini', 'Choma', 'Igurubi', 'Kinungu', 'Nanga', 'Simbo'],
            'Urambo': ['Urambo Mjini', 'Imalamakoye', 'Muungano', 'Songambele', 'Vumilia'],
            'Sikonge': ['Sikonge Mjini', 'Chabutwa', 'Igombwe', 'Kiloli', 'Mipawa', 'Tutuo'],
            'Uyui': ['Goweko', 'Igalula', 'Ilolangulu', 'Kigwa', 'Magiri', 'Miswaki'],
            'Kaliua': ['Kaliua Mjini', 'Ichemba', 'Kazaroho', 'Usinge', 'Zugimlole'],
        }
    },
};

const allRegions = Object.keys(tanzanianAdministrativeData);

// Dynamic Cascading Districts based on selected Region
const availableDistricts = computed(() => {
    if (!form.region || !tanzanianAdministrativeData[form.region]) return [];
    return Object.keys(tanzanianAdministrativeData[form.region].districts);
});

// Dynamic Cascading Wards based on selected District
const availableWards = computed(() => {
    if (!form.region || !form.district || !tanzanianAdministrativeData[form.region]?.districts[form.district]) return [];
    return tanzanianAdministrativeData[form.region].districts[form.district];
});

// When region changes, automatically set first district
watch(() => form.region, (newRegion) => {
    const districts = Object.keys(tanzanianAdministrativeData[newRegion]?.districts || {});
    if (districts.length > 0 && !districts.includes(form.district)) {
        form.district = districts[0];
    }
});

// When district changes, automatically set first ward
watch(() => form.district, (newDistrict) => {
    const wards = tanzanianAdministrativeData[form.region]?.districts[newDistrict] || [];
    if (wards.length > 0 && !wards.includes(form.ward)) {
        form.ward = wards[0];
    }
});

// Age Entry Mode: 'exact_dob' | 'age_calc'
const ageEntryMode = ref('exact_dob');
const ageYears = ref('');
const ageMonths = ref('');
const ageDays = ref('');

// Calculate DOB from Years, Months, and Days
const computeDobFromAge = (years = 0, months = 0, days = 0) => {
    const y = parseInt(years, 10) || 0;
    const m = parseInt(months, 10) || 0;
    const d = parseInt(days, 10) || 0;

    if (y === 0 && m === 0 && d === 0 && ageYears.value === '' && ageMonths.value === '' && ageDays.value === '') {
        return '';
    }

    const targetDate = new Date();
    targetDate.setFullYear(targetDate.getFullYear() - y);
    targetDate.setMonth(targetDate.getMonth() - m);
    targetDate.setDate(targetDate.getDate() - d);

    const yearStr = targetDate.getFullYear();
    const monthStr = String(targetDate.getMonth() + 1).padStart(2, '0');
    const dayStr = String(targetDate.getDate()).padStart(2, '0');
    return `${yearStr}-${monthStr}-${dayStr}`;
};

// Calculate exact Age breakdown from DOB
const computedAge = computed(() => {
    if (!form.dob) return null;
    const dob = new Date(form.dob);
    if (isNaN(dob.getTime())) return null;

    const today = new Date();
    let years = today.getFullYear() - dob.getFullYear();
    let months = today.getMonth() - dob.getMonth();
    let days = today.getDate() - dob.getDate();

    if (days < 0) {
        months -= 1;
        const prevMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        days += prevMonth.getDate();
    }

    if (months < 0) {
        years -= 1;
        months += 12;
    }

    if (years < 0) return null;

    const parts = [];
    if (years > 0) parts.push(`${years} ${years === 1 ? 'Year' : 'Years'}`);
    if (months > 0 || years === 0) parts.push(`${months} ${months === 1 ? 'Month' : 'Months'}`);
    if (days > 0 || (years === 0 && months === 0)) parts.push(`${days} ${days === 1 ? 'Day' : 'Days'}`);

    return {
        years,
        months,
        days,
        formatted: parts.join(', ') || '0 Days (Neonate / Born Today)',
    };
});

// Watch Years/Months/Days inputs to update form.dob automatically in 'age_calc' mode
watch([ageYears, ageMonths, ageDays], () => {
    if (ageEntryMode.value === 'age_calc') {
        form.dob = computeDobFromAge(ageYears.value, ageMonths.value, ageDays.value);
    }
});

// Switch Age Entry Mode
const setAgeMode = (mode) => {
    ageEntryMode.value = mode;
    if (mode === 'age_calc') {
        if (computedAge.value) {
            ageYears.value = computedAge.value.years || '';
            ageMonths.value = computedAge.value.months || '';
            ageDays.value = computedAge.value.days || '';
        }
    }
};

// Tanzanian Telecom Carrier Auto-Detection
const telecomCarrier = computed(() => {
    const raw = String(form.phone || '').replace(/\s+/g, '').replace(/-/g, '');
    let prefix = '';
    if (raw.startsWith('+255')) {
        prefix = raw.slice(4, 6);
    } else if (raw.startsWith('255')) {
        prefix = raw.slice(3, 5);
    } else if (raw.startsWith('0')) {
        prefix = raw.slice(1, 3);
    }

    if (['74', '75', '76'].includes(prefix)) {
        return { name: 'Vodacom', color: 'bg-rose-50 text-rose-800 border-rose-200' };
    }
    if (['71', '65', '67'].includes(prefix)) {
        return { name: 'Tigo (Mixx)', color: 'bg-sky-50 text-sky-800 border-sky-200' };
    }
    if (['78', '79', '68', '69'].includes(prefix)) {
        return { name: 'Airtel', color: 'bg-red-50 text-red-800 border-red-200' };
    }
    if (['62'].includes(prefix)) {
        return { name: 'Halotel', color: 'bg-orange-50 text-orange-800 border-orange-200' };
    }
    if (['73'].includes(prefix)) {
        return { name: 'TTCL', color: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
    }
    return null;
});

// NIDA 20-digit formatting and validation helper
const nidaDigits = computed(() => {
    return String(form.nida || '').replace(/\D/g, '');
});

const isNidaValid = computed(() => {
    return nidaDigits.value.length === 20;
});

const handleNidaInput = (e) => {
    const raw = e.target.value.replace(/\D/g, '').slice(0, 20);
    form.nida = raw;
};

// Draft Auto-Save to SessionStorage (Zero data loss on power cuts/reloads)
const saveDraftToSession = () => {
    try {
        const payload = {
            first_name: form.first_name,
            middle_name: form.middle_name,
            last_name: form.last_name,
            dob: form.dob,
            gender: form.gender,
            blood_group: form.blood_group,
            marital_status: form.marital_status,
            occupation: form.occupation,
            nationality: form.nationality,
            phone: form.phone,
            email: form.email,
            region: form.region,
            district: form.district,
            ward: form.ward,
            street_address: form.street_address,
            timestamp: Date.now()
        };
        sessionStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {
        // Silently handle quota limits
    }
};

// Restore Draft on Mount
onMounted(() => {
    try {
        const raw = sessionStorage.getItem(DRAFT_STORAGE_KEY);
        if (raw) {
            const draft = JSON.parse(raw);
            if (draft.first_name || draft.last_name || draft.phone) {
                Object.keys(draft).forEach(k => {
                    if (k !== 'timestamp' && draft[k] !== undefined) {
                        form[k] = draft[k];
                    }
                });
                restoredDraftNotice.value = true;
            }
        }
    } catch (e) {}
});

// Auto-save on any change
watch(
    () => [form.first_name, form.last_name, form.dob, form.phone, form.street_address],
    () => saveDraftToSession(),
    { deep: true }
);

const discardDraft = () => {
    sessionStorage.removeItem(DRAFT_STORAGE_KEY);
    form.reset();
    restoredDraftNotice.value = false;
};

const submit = () => {
    form.post(route('patients.store'), {
        onSuccess: () => {
            sessionStorage.removeItem(DRAFT_STORAGE_KEY);
        }
    });
};
</script>

<template>
    <Head title="Register New Patient — AfyaNova Workstation" />

    <AfyaShell active-module="patients">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- 1. LEFT SIDEBAR: Patient Inflow Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Patient Registry"
                    :icon="Users"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Patient Inflow
                    </div>
                    <AfyaSidebarItem
                        label="Master Patient Index"
                        :icon="SearchIcon"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.index')"
                    />
                    <AfyaSidebarItem
                        label="Register New Patient"
                        :icon="UserPlus"
                        :active="true"
                        :collapsed="state === 'collapsed'"
                    />
                    <AfyaSidebarItem
                        label="Live Queue & Triage"
                        :icon="Clock"
                        :collapsed="state === 'collapsed'"
                        :href="route('queue.index')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: High-Density Registration Desk (Full Width) -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Patient Registry', href: route('patients.index') },
                        { label: 'Register Patient', active: true }
                    ]"
                >
                    <template #actions>
                        <Link :href="route('patients.index')">
                            <Button variant="outline" size="sm" class="h-7 text-xs gap-1">
                                <ArrowLeft class="w-3 h-3" />
                                <span>MPI Directory</span>
                            </Button>
                        </Link>
                    </template>

                    <!-- Restored Draft Recovery Notification -->
                    <div
                        v-if="restoredDraftNotice"
                        class="mb-2 px-3 py-1.5 bg-sky-50 border border-sky-200 rounded flex items-center justify-between text-xs text-sky-800 shadow-2xs"
                    >
                        <div class="flex items-center gap-1.5">
                            <History class="w-3.5 h-3.5 text-sky-600 flex-shrink-0" />
                            <span><strong>Session Draft Restored:</strong> Unsaved patient data was recovered safely from your previous session.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="discardDraft"
                                class="text-[11px] font-semibold text-rose-700 hover:underline"
                            >
                                Discard Draft
                            </button>
                            <button
                                type="button"
                                @click="restoredDraftNotice = false"
                                class="text-sky-600 hover:text-sky-900"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- High-Density Clinical Registration Form Sheet -->
                    <form @submit.prevent="submit" class="w-full bg-card border border-border rounded-lg p-3.5 shadow-2xs space-y-3.5">
                        
                        <!-- SECTION 1: Patient Identity & Demographics -->
                        <div class="space-y-2" @focusin="activeSection = 'demographics'">
                            <div class="flex items-center justify-between border-b border-border pb-1">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-foreground uppercase tracking-wider">
                                    <Users class="w-3.5 h-3.5 text-primary" />
                                    <span>1. Patient Identity & Demographics</span>
                                </div>
                                <span class="text-[10px] text-muted-foreground">Master Patient Record</span>
                            </div>

                            <!-- 3-Column Names -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">
                                        First Name
                                        <span class="text-[9px] text-muted-foreground/80 font-normal lowercase ml-1">(required)</span>
                                    </label>
                                    <Input
                                        v-model="form.first_name"
                                        type="text"
                                        placeholder="e.g. Asha"
                                        :error="!!form.errors.first_name"
                                        class="h-7 text-xs"
                                        required
                                        autofocus
                                    />
                                    <InputError :message="form.errors.first_name" class="mt-0.5" />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Middle Name</label>
                                    <Input
                                        v-model="form.middle_name"
                                        type="text"
                                        placeholder="e.g. Juma"
                                        :error="!!form.errors.middle_name"
                                        class="h-7 text-xs"
                                    />
                                    <InputError :message="form.errors.middle_name" class="mt-0.5" />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">
                                        Last Name
                                        <span class="text-[9px] text-muted-foreground/80 font-normal lowercase ml-1">(required)</span>
                                    </label>
                                    <Input
                                        v-model="form.last_name"
                                        type="text"
                                        placeholder="e.g. Rashid"
                                        :error="!!form.errors.last_name"
                                        class="h-7 text-xs"
                                        required
                                    />
                                    <InputError :message="form.errors.last_name" class="mt-0.5" />
                                </div>
                            </div>

                            <!-- Dual Age/DOB Calculator & Clinical Basics (Dense Grid) -->
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 pt-0.5 items-start">
                                
                                <!-- Age & DOB Compact Calculator (7 cols) -->
                                <div class="lg:col-span-7 p-2 rounded border border-border bg-muted/20 space-y-1.5" @focusin="activeSection = 'dob_age'">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1 text-[11px] font-bold text-foreground">
                                            <Calendar class="w-3 h-3 text-primary" />
                                            <span>Age & Date of Birth</span>
                                        </div>
                                        <div class="flex items-center gap-1 bg-muted p-0.5 rounded border border-border/60">
                                            <button
                                                type="button"
                                                @click="setAgeMode('exact_dob')"
                                                :class="[
                                                    'px-2 py-0.2 text-[10px] font-medium rounded transition-colors',
                                                    ageEntryMode === 'exact_dob'
                                                        ? 'bg-card text-foreground font-bold shadow-2xs'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                ]"
                                            >
                                                Exact DOB
                                            </button>
                                            <button
                                                type="button"
                                                @click="setAgeMode('age_calc')"
                                                :class="[
                                                    'px-2 py-0.2 text-[10px] font-medium rounded transition-colors',
                                                    ageEntryMode === 'age_calc'
                                                        ? 'bg-card text-foreground font-bold shadow-2xs'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                ]"
                                            >
                                                Age (Y / M / D)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Exact DOB Input Mode -->
                                    <div v-if="ageEntryMode === 'exact_dob'" class="flex items-center gap-2">
                                        <div class="w-1/2">
                                            <Input
                                                v-model="form.dob"
                                                type="date"
                                                :max="new Date().toISOString().split('T')[0]"
                                                :error="!!form.errors.dob"
                                                class="h-7 text-xs"
                                            />
                                        </div>
                                        <div class="w-1/2 min-w-0">
                                            <div v-if="computedAge" class="flex items-center gap-1 text-[11px] text-primary font-bold truncate">
                                                <Sparkles class="w-3 h-3 flex-shrink-0" />
                                                <span class="truncate font-mono">{{ computedAge.formatted }}</span>
                                            </div>
                                            <div v-else class="text-[10px] text-muted-foreground italic truncate">
                                                Select date of birth
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Years / Months / Days Input Mode -->
                                    <div v-else class="space-y-1">
                                        <div class="grid grid-cols-3 gap-1.5">
                                            <div>
                                                <Input
                                                    v-model="ageYears"
                                                    type="number"
                                                    min="0"
                                                    max="130"
                                                    placeholder="Years (0-130)"
                                                    class="h-7 text-xs font-mono text-center"
                                                />
                                            </div>
                                            <div>
                                                <Input
                                                    v-model="ageMonths"
                                                    type="number"
                                                    min="0"
                                                    max="11"
                                                    placeholder="Months (0-11)"
                                                    class="h-7 text-xs font-mono text-center"
                                                />
                                            </div>
                                            <div>
                                                <Input
                                                    v-model="ageDays"
                                                    type="number"
                                                    min="0"
                                                    max="30"
                                                    placeholder="Days (0-30)"
                                                    class="h-7 text-xs font-mono text-center"
                                                />
                                            </div>
                                        </div>
                                        <div v-if="form.dob" class="text-[10px] text-muted-foreground flex items-center justify-between font-mono px-1">
                                            <span>Computed Birth Date: <strong class="text-foreground">{{ form.dob }}</strong></span>
                                            <span class="text-primary font-semibold">Auto-Calculated</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gender, Blood Group, Marital Status (5 cols) -->
                                <div class="lg:col-span-5 grid grid-cols-3 gap-1.5">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-foreground mb-0.5">
                                            Gender
                                            <span class="text-[9px] text-muted-foreground/80 font-normal lowercase ml-0.5">(req)</span>
                                        </label>
                                        <Select
                                            v-model="form.gender"
                                            size="sm"
                                            :options="['Female', 'Male', 'Other', 'Unknown']"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-semibold text-foreground mb-0.5">Blood Group</label>
                                        <Select
                                            v-model="form.blood_group"
                                            size="sm"
                                            placeholder="None"
                                            :options="['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']"
                                            class="font-mono"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-semibold text-foreground mb-0.5">Marital Status</label>
                                        <Select
                                            v-model="form.marital_status"
                                            size="sm"
                                            placeholder="Select..."
                                            :options="['Single', 'Married', 'Divorced', 'Widowed']"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: Residential Catchment & Administrative Address -->
                        <div class="space-y-2" @focusin="activeSection = 'address'">
                            <div class="flex items-center justify-between border-b border-border pb-1">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-foreground uppercase tracking-wider">
                                    <MapPin class="w-3.5 h-3.5 text-primary" />
                                    <span>2. Residential Catchment & Administrative Address (Makazi na Asili — HMIS/MTUHA)</span>
                                </div>
                                <span class="text-[10px] text-muted-foreground">Cascading Geographic Routing</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                                <!-- Region (Mkoa) Dropdown -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Region (Mkoa)</label>
                                    <Select
                                        v-model="form.region"
                                        size="sm"
                                        :options="allRegions"
                                    />
                                    <InputError :message="form.errors.region" class="mt-0.5" />
                                </div>

                                <!-- District (Wilaya) Dropdown -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">District (Wilaya)</label>
                                    <Select
                                        v-if="availableDistricts.length > 0"
                                        v-model="form.district"
                                        size="sm"
                                        :options="availableDistricts"
                                    />
                                    <Input
                                        v-else
                                        v-model="form.district"
                                        type="text"
                                        placeholder="e.g. Ilala, Kinondoni"
                                        class="h-7 text-xs"
                                    />
                                    <InputError :message="form.errors.district" class="mt-0.5" />
                                </div>

                                <!-- Ward (Kata) Dropdown -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Ward (Kata)</label>
                                    <Select
                                        v-if="availableWards.length > 0"
                                        v-model="form.ward"
                                        size="sm"
                                        :options="availableWards"
                                    />
                                    <Input
                                        v-else
                                        v-model="form.ward"
                                        type="text"
                                        placeholder="e.g. Kariakoo, Upanga"
                                        class="h-7 text-xs"
                                    />
                                    <InputError :message="form.errors.ward" class="mt-0.5" />
                                </div>

                                <!-- Street / House No. -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Street / House No. (Mtaa / Kijiji)</label>
                                    <Input
                                        v-model="form.street_address"
                                        type="text"
                                        placeholder="e.g. Msimbazi St, No. 14"
                                        class="h-7 text-xs"
                                    />
                                    <InputError :message="form.errors.street_address" class="mt-0.5" />
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 3: Identification, Telecom & Contact Details -->
                        <div class="space-y-2" @focusin="activeSection = 'contacts'">
                            <div class="flex items-center justify-between border-b border-border pb-1">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-foreground uppercase tracking-wider">
                                    <Building2 class="w-3.5 h-3.5 text-primary" />
                                    <span>3. Identification, Telecom & Profession</span>
                                </div>
                                <span class="text-[10px] text-muted-foreground">National Deduplication</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
                                <!-- Phone with Carrier Badge -->
                                <div class="lg:col-span-1">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <label class="text-[11px] font-semibold text-foreground">Phone</label>
                                        <span
                                            v-if="telecomCarrier"
                                            :class="['text-[9px] font-bold px-1.5 py-0.2 rounded border', telecomCarrier.color]"
                                        >
                                            {{ telecomCarrier.name }}
                                        </span>
                                    </div>
                                    <Input
                                        v-model="form.phone"
                                        type="tel"
                                        placeholder="0712345678"
                                        :error="!!form.errors.phone"
                                        class="h-7 font-mono text-xs"
                                    />
                                    <InputError :message="form.errors.phone" class="mt-0.5" />
                                </div>

                                <!-- NIDA 20-Digit Input -->
                                <div class="lg:col-span-1">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <label class="text-[11px] font-semibold text-foreground">NIDA (20 Digits)</label>
                                        <span 
                                            class="text-[9px] font-mono font-bold"
                                            :class="isNidaValid ? 'text-emerald-700' : 'text-muted-foreground'"
                                        >
                                            {{ nidaDigits.length }}/20
                                            <Check v-if="isNidaValid" class="w-2.5 h-2.5 inline text-emerald-600 ml-0.5" />
                                        </span>
                                    </div>
                                    <Input
                                        :model-value="form.nida"
                                        type="text"
                                        placeholder="19950820..."
                                        maxlength="20"
                                        :error="!!form.errors.nida"
                                        class="h-7 font-mono text-xs tracking-wider"
                                        @input="handleNidaInput"
                                    />
                                    <InputError :message="form.errors.nida" class="mt-0.5" />
                                </div>

                                <!-- Occupation -->
                                <div class="lg:col-span-1">
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Occupation</label>
                                    <Input
                                        v-model="form.occupation"
                                        type="text"
                                        placeholder="e.g. Accountant"
                                        class="h-7 text-xs"
                                    />
                                </div>

                                <!-- Nationality -->
                                <div class="lg:col-span-1">
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Nationality</label>
                                    <Input
                                        v-model="form.nationality"
                                        type="text"
                                        placeholder="Tanzanian"
                                        class="h-7 text-xs"
                                    />
                                </div>

                                <!-- Email -->
                                <div class="lg:col-span-1">
                                    <label class="block text-[11px] font-semibold text-foreground mb-0.5">Email Address</label>
                                    <Input
                                        v-model="form.email"
                                        type="email"
                                        placeholder="patient@example.com"
                                        :error="!!form.errors.email"
                                        class="h-7 text-xs"
                                    />
                                    <InputError :message="form.errors.email" class="mt-0.5" />
                                </div>
                            </div>
                        </div>

                        <!-- Form Submit / Cancel Bar -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                            <Link :href="route('patients.index')">
                                <Button type="button" variant="outline" size="sm" class="h-7 text-xs">
                                    Cancel
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                variant="default"
                                size="sm"
                                :disabled="form.processing"
                                class="h-7 text-xs gap-1.5"
                            >
                                <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                                <UserPlus v-else class="w-3.5 h-3.5" />
                                <span>{{ form.processing ? 'Registering...' : 'Register Patient & Generate MRN' }}</span>
                            </Button>
                        </div>
                    </form>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Dynamic High-Density Context-Aware Clinical Assistant -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Clinical Assistant"
                    :icon="Activity"
                    :width="width"
                    @close="close"
                >
                    <div class="space-y-2 text-xs">
                        
                        <!-- Quick Patient Intake Profile Summary -->
                        <div class="p-2 rounded bg-card border border-border/80 space-y-1">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Intake Profile</span>
                                <span class="text-primary font-mono font-semibold">{{ form.gender || 'Pending' }}</span>
                            </div>
                            <div class="text-xs font-bold text-foreground truncate">
                                {{ (form.first_name || form.last_name) ? `${form.first_name} ${form.last_name}` : 'New Patient' }}
                            </div>
                            <div v-if="computedAge" class="text-[10px] font-mono text-primary font-bold flex items-center gap-1">
                                <Sparkles class="w-2.5 h-2.5" />
                                <span class="truncate">{{ computedAge.formatted }}</span>
                            </div>
                        </div>

                        <!-- 1. DYNAMIC PANEL: Immunization Protocol (Active during Age / DOB focus) -->
                        <div v-if="activeSection === 'dob_age'" class="p-2 rounded bg-card border border-border/80 space-y-1.5">
                            <div class="flex items-center gap-1 text-primary font-bold text-[11px]">
                                <Syringe class="w-3 h-3 flex-shrink-0" />
                                <span class="truncate">Tanzania EPI Schedule</span>
                            </div>
                            <div class="space-y-1 text-[10px]">
                                <div class="p-1 rounded bg-muted/60 border border-border/60 flex items-center justify-between">
                                    <span class="font-bold text-primary font-mono">Birth:</span>
                                    <span class="text-foreground truncate">BCG · OPV 0</span>
                                </div>
                                <div class="p-1 rounded bg-muted/60 border border-border/60 flex items-center justify-between">
                                    <span class="font-bold text-primary font-mono">6 Wks:</span>
                                    <span class="text-foreground truncate">DTP-HepB-Hib 1 · Rota 1</span>
                                </div>
                                <div class="p-1 rounded bg-muted/60 border border-border/60 flex items-center justify-between">
                                    <span class="font-bold text-primary font-mono">9 Mos:</span>
                                    <span class="text-foreground truncate">MR 1 · Vitamin A</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. DYNAMIC PANEL: Geographic Routing Hierarchy (Active during Address focus) -->
                        <div v-else-if="activeSection === 'address'" class="p-2 rounded bg-card border border-border/80 space-y-1.5">
                            <div class="flex items-center gap-1 text-primary font-bold text-[11px]">
                                <MapPin class="w-3 h-3 flex-shrink-0" />
                                <span class="truncate">Catchment HMIS Route</span>
                            </div>
                            <div class="space-y-1 text-[10px]">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Region:</span>
                                    <span class="font-bold text-foreground font-mono truncate max-w-[140px]">{{ form.region }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">District:</span>
                                    <span class="font-bold text-primary font-mono truncate max-w-[140px]">{{ form.district }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Ward:</span>
                                    <span class="font-bold text-foreground font-mono truncate max-w-[140px]">{{ form.ward }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. DYNAMIC PANEL: Identification & Contacts (Active during NIDA/Contacts focus) -->
                        <div v-else-if="activeSection === 'contacts'" class="p-2 rounded bg-card border border-border/80 space-y-1.5">
                            <div class="flex items-center gap-1 text-primary font-bold text-[11px]">
                                <ShieldCheck class="w-3 h-3 flex-shrink-0" />
                                <span class="truncate">NIDA & Telecom Standards</span>
                            </div>
                            <div class="text-[10px] space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Carrier:</span>
                                    <span v-if="telecomCarrier" :class="['px-1.5 py-0.2 rounded font-bold text-[9px]', telecomCarrier.color]">{{ telecomCarrier.name }}</span>
                                    <span v-else class="text-muted-foreground font-mono">—</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">NIDA Length:</span>
                                    <span class="font-mono font-bold" :class="isNidaValid ? 'text-emerald-700' : 'text-muted-foreground'">{{ nidaDigits.length }}/20 digits</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. DEFAULT PANEL: Master Patient Index Policy -->
                        <div v-else class="p-2 rounded bg-card border border-border/80 space-y-1.5">
                            <div class="flex items-center gap-1 text-primary font-bold text-[11px]">
                                <FileText class="w-3 h-3 flex-shrink-0" />
                                <span class="truncate">MPI Protocols</span>
                            </div>
                            <div class="text-[10px] text-muted-foreground space-y-0.5 leading-tight">
                                <p>• Permanent MRN auto-generated.</p>
                                <p>• Real-time local draft recovery.</p>
                                <p>• NIDA national deduplication check.</p>
                            </div>
                        </div>

                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
