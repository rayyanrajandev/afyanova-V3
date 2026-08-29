<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/ui/Button.vue';
import AfyaThemeToggle from '@/Components/Afya/AfyaThemeToggle.vue';
import { 
    Stethoscope, 
    Receipt, 
    Users, 
    Clock, 
    Pill, 
    ShieldCheck, 
    Activity, 
    ArrowRight, 
    CheckCircle2, 
    Lock, 
    Database, 
    Sparkles,
    Building2,
    FlaskConical,
    ChevronRight,
    Search
} from '@lucide/vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    laravelVersion: {
        type: String,
        default: '12.x',
    },
    phpVersion: {
        type: String,
        default: '8.5',
    },
});

const modules = [
    {
        title: 'Clinical Workstation',
        icon: Stethoscope,
        badge: 'Sub-Second Charting',
        color: 'text-primary bg-primary/10 border-primary/20',
        description: 'Structured SOAP clinical notes, digital signing with SHA-256 cryptographic immutability, automated BMI vitals triage, and real-time drug allergy cross-reactivity guards.',
        href: 'workspace.clinical',
        linkText: 'Launch Clinical Desk'
    },
    {
        title: 'Cashier & Double-Entry Ledger',
        icon: Receipt,
        badge: 'Zero Revenue Leakage',
        color: 'text-amber-700 bg-amber-50 border-amber-200',
        description: 'Audited double-entry financial ledger enforcing strict debit/credit balance invariance. Multi-tender POS settlement for Cash, Insurance, and Tanzanian Mobile Money STK.',
        href: 'billing.desk',
        linkText: 'Open Cashier POS'
    },
    {
        title: 'Live Patient Triage Queue',
        icon: Clock,
        badge: 'Real-Time Telemetry',
        color: 'text-sky-700 bg-sky-50 border-sky-200',
        description: 'Multi-stage patient flow from reception to triage, consultation, lab, and pharmacy. 1-click calling station with estimated wait-time telemetry.',
        href: 'queue.index',
        linkText: 'View Live Queue'
    },
    {
        title: 'Pharmacy Dispensing Desk',
        icon: Pill,
        badge: 'Formulary & Cross-Check',
        color: 'text-emerald-700 bg-emerald-50 border-emerald-200',
        description: 'Real-time e-prescription queue, drug-allergy contraindication overrides with mandatory clinical rationale, and batch tracking.',
        href: 'pharmacy.queue',
        linkText: 'Access Pharmacy Desk'
    },
    {
        title: 'Master Patient Index (MPI)',
        icon: Users,
        badge: 'NIDA & MRN Unique',
        color: 'text-indigo-700 bg-indigo-50 border-indigo-200',
        description: 'Unified 360-degree patient file, chronological clinical timeline, contact graph, national NIDA verification, and instant fuzzy searching.',
        href: 'patients.index',
        linkText: 'Explore Master Index'
    },
    {
        title: 'Multi-Tenant Security & Audit',
        icon: ShieldCheck,
        badge: 'Row-Level Isolation',
        color: 'text-rose-700 bg-rose-50 border-rose-200',
        description: 'Strict PostgreSQL Row-Level Security (RLS) and BelongsToTenant scoping. Immutable audit trails tracking every clinical and financial data mutation.',
        href: 'dashboard',
        linkText: 'System Governance'
    }
];

const standards = [
    { name: 'NHIF e-Claim Compatible', desc: 'Pre-authorization & tariff mapping' },
    { name: 'NIDA Verification Ready', desc: 'National ID identity validation' },
    { name: 'Mobile Money STK Push', desc: 'M-Pesa, Airtel Money, Tigo Pesa' },
    { name: 'FHIR R4 Interoperability', desc: 'HL7 international data facade' },
];
</script>

<template>
    <Head title="AfyaNova V3 — Next-Gen Hospital Information System" />

    <div class="min-h-screen bg-background text-foreground flex flex-col selection:bg-primary selection:text-primary-foreground">
        
        <!-- ============================================================== -->
        <!-- 1. TOP NAVIGATION BAR                                           -->
        <!-- ============================================================== -->
        <header class="sticky top-0 z-50 w-full border-b border-border bg-background/90 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                
                <!-- Brand Logo -->
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationLogo />
                </Link>

                <!-- Navigation Modules -->
                <nav class="hidden md:flex items-center gap-6 text-xs font-semibold text-muted-foreground">
                    <Link :href="route('workspace.clinical')" class="hover:text-foreground transition-colors flex items-center gap-1.5">
                        <Stethoscope class="w-3.5 h-3.5 text-primary" />
                        <span>Clinical Desk</span>
                    </Link>
                    <Link :href="route('queue.index')" class="hover:text-foreground transition-colors flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5 text-sky-600" />
                        <span>Triage Queue</span>
                    </Link>
                    <Link :href="route('billing.desk')" class="hover:text-foreground transition-colors flex items-center gap-1.5">
                        <Receipt class="w-3.5 h-3.5 text-amber-600" />
                        <span>Cashier & Billing</span>
                    </Link>
                    <Link :href="route('pharmacy.queue')" class="hover:text-foreground transition-colors flex items-center gap-1.5">
                        <Pill class="w-3.5 h-3.5 text-emerald-600" />
                        <span>Pharmacy</span>
                    </Link>
                    <Link :href="route('patients.index')" class="hover:text-foreground transition-colors flex items-center gap-1.5">
                        <Users class="w-3.5 h-3.5 text-indigo-600" />
                        <span>Patient MPI</span>
                    </Link>
                </nav>

                <!-- Auth / Workstation CTAs -->
                <div class="flex items-center gap-2">
                    <AfyaThemeToggle />

                    <template v-if="$page.props.auth?.user">
                        <Link :href="route('dashboard')">
                            <Button variant="default" size="sm" class="h-8 text-xs font-bold gap-1.5 shadow-xs">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Workstation Dashboard</span>
                                <ArrowRight class="w-3.5 h-3.5" />
                            </Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')">
                            <Button variant="ghost" size="sm" class="h-8 text-xs font-semibold">
                                Staff Sign In
                            </Button>
                        </Link>
                        <Link v-if="canRegister" :href="route('register')">
                            <Button variant="default" size="sm" class="h-8 text-xs font-bold gap-1.5 shadow-xs">
                                <span>Onboard Facility</span>
                                <ArrowRight class="w-3.5 h-3.5" />
                            </Button>
                        </Link>
                    </template>
                </div>

            </div>
        </header>

        <!-- ============================================================== -->
        <!-- 2. HERO SECTION                                                -->
        <!-- ============================================================== -->
        <section class="relative pt-12 pb-16 md:pt-20 md:pb-24 overflow-hidden border-b border-border/60">
            <!-- Subtle Radial Gradient Background -->
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(16,185,129,0.12),rgba(255,255,255,0))]"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-6">
                
                <!-- Regional Badge -->
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20 shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>Tanzanian & African Healthcare Information System</span>
                    <span class="text-muted-foreground font-mono">· V3.0</span>
                </div>

                <!-- Main Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground max-w-4xl mx-auto leading-[1.12]">
                    High-Performance Hospital Operating System & EHR.
                </h1>

                <!-- Sub-Headline -->
                <p class="text-sm sm:text-base lg:text-lg text-muted-foreground max-w-2xl mx-auto leading-relaxed">
                    Designed for private, faith-based, and public healthcare facilities. Sub-second clinical charting, double-entry financial ledgers, and zero revenue leakage.
                </p>

                <!-- Fast Module Launch Strip -->
                <div class="pt-4 flex flex-wrap items-center justify-center gap-3">
                    <Link :href="route('workspace.clinical')">
                        <Button variant="default" size="default" class="h-10 px-5 text-xs font-bold gap-2 shadow-sm">
                            <Stethoscope class="w-4 h-4" />
                            <span>Clinical Workstation</span>
                        </Button>
                    </Link>

                    <Link :href="route('queue.index')">
                        <Button variant="outline" size="default" class="h-10 px-5 text-xs font-bold gap-2 bg-card">
                            <Clock class="w-4 h-4 text-sky-600" />
                            <span>Live Patient Queue</span>
                        </Button>
                    </Link>

                    <Link :href="route('billing.desk')">
                        <Button variant="outline" size="default" class="h-10 px-5 text-xs font-bold gap-2 bg-card">
                            <Receipt class="w-4 h-4 text-amber-600" />
                            <span>Cashier Billing Desk</span>
                        </Button>
                    </Link>
                </div>

                <!-- Live System Telemetry Chips -->
                <div class="pt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl mx-auto text-left">
                    <div class="p-2.5 rounded-md bg-card border border-border space-y-0.5 shadow-2xs">
                        <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Clinical Speed</div>
                        <div class="text-base font-mono font-bold text-primary">&lt; 150ms</div>
                        <div class="text-[10px] text-muted-foreground">Sub-second charting</div>
                    </div>

                    <div class="p-2.5 rounded-md bg-card border border-border space-y-0.5 shadow-2xs">
                        <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Financial Invariant</div>
                        <div class="text-base font-mono font-bold text-emerald-700">100% Balanced</div>
                        <div class="text-[10px] text-muted-foreground">&Sigma; Debits == &Sigma; Credits</div>
                    </div>

                    <div class="p-2.5 rounded-md bg-card border border-border space-y-0.5 shadow-2xs">
                        <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Note Veracity</div>
                        <div class="text-base font-mono font-bold text-foreground">SHA-256 Hash</div>
                        <div class="text-[10px] text-muted-foreground">Cryptographic signature</div>
                    </div>

                    <div class="p-2.5 rounded-md bg-card border border-border space-y-0.5 shadow-2xs">
                        <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Tenant Isolation</div>
                        <div class="text-base font-mono font-bold text-indigo-700">PostgreSQL RLS</div>
                        <div class="text-[10px] text-muted-foreground">Multi-Facility Scoped</div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 3. CORE HIS MODULES GRID                                       -->
        <!-- ============================================================== -->
        <section class="py-14 bg-muted/20 border-b border-border/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
                
                <div class="text-center space-y-2 max-w-xl mx-auto">
                    <h2 class="text-2xl font-extrabold tracking-tight text-foreground">
                        Modular Monolith Clinical & ERP Architecture
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Strictly bounded domains engineered with Domain-Driven Design (DDD) to eliminate system spaghetti and data corruption.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="mod in modules" 
                        :key="mod.title"
                        class="p-5 rounded-lg bg-card border border-border hover:border-primary/50 transition-all space-y-3 flex flex-col justify-between shadow-2xs"
                    >
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="w-8 h-8 rounded-md flex items-center justify-center border" :class="mod.color">
                                    <component :is="mod.icon" class="w-4 h-4" />
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-muted text-foreground border border-border font-mono">
                                    {{ mod.badge }}
                                </span>
                            </div>

                            <h3 class="text-sm font-bold text-foreground">{{ mod.title }}</h3>
                            <p class="text-xs text-muted-foreground leading-relaxed">{{ mod.description }}</p>
                        </div>

                        <div class="pt-3 border-t border-border/50">
                            <Link :href="route(mod.href)" class="text-xs font-bold text-primary hover:underline flex items-center justify-between group">
                                <span>{{ mod.linkText }}</span>
                                <ChevronRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 4. TANZANIAN HEALTHCARE INTEGRATION STRIP                       -->
        <!-- ============================================================== -->
        <section class="py-12 bg-background border-b border-border/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border">
                    <div>
                        <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                            <Building2 class="w-4 h-4 text-primary" />
                            <span>Tanzanian Healthcare Interoperability & Compliance</span>
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Engineered from the ground up for the Tanzanian regulatory and financial landscape.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            Ministry of Health Ready
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div 
                        v-for="std in standards" 
                        :key="std.name"
                        class="p-3 rounded-md bg-card border border-border space-y-1 shadow-2xs"
                    >
                        <div class="flex items-center gap-1.5 text-xs font-bold text-foreground">
                            <CheckCircle2 class="w-3.5 h-3.5 text-primary shrink-0" />
                            <span>{{ std.name }}</span>
                        </div>
                        <p class="text-[11px] text-muted-foreground pl-5">{{ std.desc }}</p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 5. FOOTER                                                      -->
        <!-- ============================================================== -->
        <footer class="mt-auto py-8 bg-card border-t border-border text-xs text-muted-foreground">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
                
                <div class="flex items-center gap-2">
                    <ApplicationLogo />
                    <span class="text-border">|</span>
                    <span>Dar es Salaam Medical Center · Multi-Tenant Cluster</span>
                </div>

                <div class="flex items-center gap-4 text-[11px]">
                    <Link :href="route('login')" class="hover:text-foreground">Staff Login</Link>
                    <Link :href="route('register')" class="hover:text-foreground">Register Facility</Link>
                    <Link :href="route('workspace.clinical')" class="hover:text-foreground">Clinical Desk</Link>
                    <Link :href="route('billing.desk')" class="hover:text-foreground">Billing Desk</Link>
                </div>

                <div class="text-[11px] font-mono text-muted-foreground">
                    Laravel {{ laravelVersion }} (PHP v{{ phpVersion }}) · AfyaNova V3
                </div>

            </div>
        </footer>

    </div>
</template>
