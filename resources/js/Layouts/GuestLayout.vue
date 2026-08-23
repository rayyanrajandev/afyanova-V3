<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AfyaThemeToggle from '@/Components/Afya/AfyaThemeToggle.vue';
import { 
    ShieldCheck, 
    Stethoscope, 
    Lock, 
    Receipt, 
    Activity,
    CheckCircle2
} from '@lucide/vue';
</script>

<template>
    <div class="min-h-screen w-full flex bg-background text-foreground">
        
        <!-- Left Side Banner (Enterprise Hospital Visual & Telemetry) -->
        <div class="hidden lg:flex lg:w-1/2 bg-slate-900 text-slate-100 flex-col justify-between p-10 relative overflow-hidden border-r border-border">
            <!-- Subtle Grid Graphic Background -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-30"></div>
            
            <div class="relative z-10">
                <Link href="/" class="inline-flex">
                    <ApplicationLogo :inverted="true" />
                </Link>
            </div>

            <!-- Central Pitch & Key Highlights -->
            <div class="relative z-10 space-y-6 max-w-md my-auto">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-primary/20 text-emerald-400 border border-primary/30">
                    <Activity class="w-3.5 h-3.5" />
                    <span>Real-Time Clinical & Financial Telemetry</span>
                </div>
                
                <h1 class="text-3xl font-extrabold tracking-tight text-white leading-tight">
                    Next-Generation Hospital Information & EHR System.
                </h1>
                
                <p class="text-sm text-slate-300 leading-relaxed">
                    Designed for Tanzanian and African healthcare facilities with sub-second clinical charting, double-entry financial ledgers, and live patient queue management.
                </p>

                <!-- Value Props -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center gap-2.5 text-xs text-slate-200">
                        <div class="w-5 h-5 rounded bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0 border border-emerald-500/30">
                            <CheckCircle2 class="w-3.5 h-3.5" />
                        </div>
                        <span>Legally immutable clinical notes with SHA-256 digital signatures</span>
                    </div>

                    <div class="flex items-center gap-2.5 text-xs text-slate-200">
                        <div class="w-5 h-5 rounded bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0 border border-emerald-500/30">
                            <CheckCircle2 class="w-3.5 h-3.5" />
                        </div>
                        <span>Double-entry accounting ledger with debit/credit balance enforcement</span>
                    </div>

                    <div class="flex items-center gap-2.5 text-xs text-slate-200">
                        <div class="w-5 h-5 rounded bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0 border border-emerald-500/30">
                            <CheckCircle2 class="w-3.5 h-3.5" />
                        </div>
                        <span>Row-Level Security (RLS) & Multi-Facility data isolation</span>
                    </div>
                </div>
            </div>

            <!-- Footer Badge -->
            <div class="relative z-10 flex items-center justify-between text-[11px] text-slate-400 border-t border-slate-800 pt-4">
                <span>AfyaNova V3 · Healthcare Operating System</span>
                <span class="font-mono text-emerald-400 flex items-center gap-1">
                    <ShieldCheck class="w-3.5 h-3.5" />
                    HIPAA & Tanzanian Health Standards Ready
                </span>
            </div>
        </div>

        <!-- Right Side: Clean Form Container -->
        <div class="w-full lg:w-1/2 flex flex-col justify-between p-6 sm:p-12 md:p-16 relative">
            
            <div class="flex items-center justify-between mb-6">
                <div class="lg:hidden">
                    <Link href="/">
                        <ApplicationLogo />
                    </Link>
                </div>
                <div class="ml-auto">
                    <AfyaThemeToggle />
                </div>
            </div>

            <div class="w-full max-w-md mx-auto my-auto space-y-6">
                <slot />
            </div>

            <div class="mt-8 text-center text-xs text-muted-foreground">
                Protected by AfyaNova Security & Audit Protocols · &copy; {{ new Date().getFullYear() }} AfyaNova V3
            </div>
        </div>

    </div>
</template>
