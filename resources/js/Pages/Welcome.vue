<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-[#F4F9F6] font-sans text-slate-800" dir="rtl">

        <!-- ── Navbar ── -->
        <header class="sticky top-0 z-50 border-b border-slate-100 bg-white/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <!-- Logo -->
                <div class="flex items-center gap-2.5">
                    <ApplicationLogo class="h-8 w-8 object-contain" />
                    <span class="text-lg font-bold text-slate-900">Namain</span>
                </div>

                <!-- CTAs -->
                <div class="flex items-center gap-3">
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="text-sm font-medium text-slate-600 transition hover:text-emerald-600"
                    >
                        تسجيل الدخول
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500"
                    >
                        أنشئ مؤسستك
                    </Link>
                </div>
            </div>
        </header>

        <!-- ── Hero ── -->
        <section class="relative overflow-hidden px-6 py-20 md:py-28">
            <!-- Subtle background blobs -->
            <div class="pointer-events-none absolute -top-32 right-0 h-[500px] w-[500px] rounded-full bg-emerald-100/60 blur-3xl" />
            <div class="pointer-events-none absolute bottom-0 left-0 h-[400px] w-[400px] rounded-full bg-emerald-50/80 blur-3xl" />

            <div class="relative mx-auto grid max-w-7xl grid-cols-1 items-center gap-16 md:grid-cols-2">
                <!-- Text (right in RTL) -->
                <div>
                    <span class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        منصة إدارة الأعمال
                    </span>

                    <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                        أدر أعمالك،
                        <span class="text-emerald-600">ببساطة.</span>
                    </h1>

                    <p class="mt-5 max-w-lg text-base leading-relaxed text-slate-500 sm:text-lg">
                        الفواتير، المصروفات، الشيكات، والمدفوعات — كل شيء في مكان واحد. مصمم للأعمال الحديثة التي تبحث عن الرقي والكفاءة.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-xl bg-emerald-600 px-7 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-500"
                        >
                            أنشئ مؤسستك الآن
                        </Link>
                        <button class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-7 py-3.5 text-base font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M6.3 2.84A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.27l9.344-5.891a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                </svg>
                            </span>
                            مشاهدة العرض التجريبي
                        </button>
                    </div>
                </div>

                <!-- Dashboard mockup (left in RTL) -->
                <div class="relative" dir="ltr">
                    <div class="relative rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/80 overflow-hidden">
                        <!-- Mockup top bar -->
                        <div class="flex items-center gap-1.5 border-b border-slate-100 bg-slate-50 px-4 py-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-400" />
                            <span class="h-2.5 w-2.5 rounded-full bg-yellow-400" />
                            <span class="h-2.5 w-2.5 rounded-full bg-green-400" />
                            <span class="mx-auto text-xs font-medium text-slate-400">Business Namain</span>
                        </div>
                        <!-- Mockup content -->
                        <div class="p-5">
                            <!-- Stats row -->
                            <div class="mb-4 grid grid-cols-3 gap-3">
                                <div v-for="(stat, i) in [
                                    { label: 'إجمالي المبيعات', value: '٢٤٥,٠٠٠', color: 'bg-emerald-50 text-emerald-700' },
                                    { label: 'المصروفات', value: '٨٢,٣٠٠', color: 'bg-orange-50 text-orange-600' },
                                    { label: 'صافي الربح', value: '١٦٢,٧٠٠', color: 'bg-blue-50 text-blue-700' },
                                ]" :key="i" class="rounded-xl p-3" :class="stat.color">
                                    <div class="text-xs opacity-70">{{ stat.label }}</div>
                                    <div class="mt-1 text-lg font-black">{{ stat.value }}</div>
                                </div>
                            </div>
                            <!-- Fake chart bars -->
                            <div class="mb-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <div class="mb-3 text-xs font-semibold text-slate-500">المبيعات مقابل المشتريات</div>
                                <div class="flex items-end justify-between gap-1 h-20">
                                    <div v-for="(h, i) in [40,65,45,80,55,90,70,85,60,95,75,88]" :key="i"
                                        class="flex-1 rounded-t-sm"
                                        :class="i % 2 === 0 ? 'bg-emerald-400' : 'bg-slate-200'"
                                        :style="`height: ${h}%`"
                                    />
                                </div>
                            </div>
                            <!-- Fake invoice row -->
                            <div class="space-y-2">
                                <div v-for="(inv, i) in [
                                    { name: 'شركة النجمة', amount: '٤,٢٠٠', status: 'مدفوع' },
                                    { name: 'مؤسسة الرشيد', amount: '٨,٧٥٠', status: 'معلق' },
                                    { name: 'شركة التقنية', amount: '٢,١٠٠', status: 'مدفوع' },
                                ]" :key="i" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                                    <span class="text-xs font-medium text-slate-700">{{ inv.name }}</span>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-semibold text-slate-800">{{ inv.amount }} ج.س</span>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="inv.status === 'مدفوع' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                        >{{ inv.status }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Bottom stat -->
                        <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-3">
                            <span class="text-xs text-slate-400">إجمالي الرصيد المتاح</span>
                            <span class="text-base font-black text-emerald-600">٤٥٢,٣٠٠.٠٠ ج.س</span>
                        </div>
                    </div>
                    <!-- Floating badge -->
                    <div class="absolute -bottom-4 -left-4 flex items-center gap-2 rounded-xl border border-slate-100 bg-white px-4 py-2.5 shadow-lg">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <div dir="rtl">
                            <div class="text-xs font-semibold text-slate-800">+12.4%</div>
                            <div class="text-xs text-slate-400">الربح الإجمالي</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Features intro ── -->
        <section class="px-6 py-20">
            <div class="mx-auto max-w-7xl">
                <div class="mb-14 text-center">
                    <h2 class="text-3xl font-black text-slate-900 sm:text-4xl">قوة التحكم في متناول يدك</h2>
                    <p class="mt-3 text-base text-slate-500">منصة "نمين" لتكون الأداة الأكثر فاعلية لإدارة شؤونك المالية دون تعقيدات المحاسبة التقليدية.</p>
                </div>

                <!-- Bento grid -->
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">

                    <!-- Card 1 — Expenses (tall, green) -->
                    <div class="flex flex-col justify-between rounded-3xl bg-emerald-600 p-8 text-white md:row-span-2">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75" />
                            </svg>
                        </div>
                        <div class="mt-auto pt-8">
                            <h3 class="text-2xl font-bold">تتبع المصروفات</h3>
                            <p class="mt-3 text-sm leading-relaxed text-emerald-100">راقب كل قرش تم صرفه، صنف مصروفاتك، واربطها بالشراء بكل سهولة.</p>
                            <button class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white/15 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/25">
                                اكتشف أكثر
                                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Card 2 — Invoices (wide, white) -->
                    <div class="col-span-1 rounded-3xl border border-slate-100 bg-white p-8 shadow-sm lg:col-span-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                            <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-xl font-bold text-slate-900">فواتير إلكترونية متكاملة</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">أصدر فواتير ضريبية متوافقة تماماً مع متطلبات هيئة الزكاة والضريبة والجمارك، أرسلها فوراً لعملائك، وتابع حالة التحصيل.</p>
                        <!-- Mini invoice mockup -->
                        <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4" dir="ltr">
                            <div class="space-y-2">
                                <div v-for="row in ['Customer','Invoice #','Amount','Status']" :key="row"
                                    class="flex items-center gap-3">
                                    <div class="h-2 w-16 rounded bg-slate-200" />
                                    <div class="h-2 flex-1 rounded" :class="row === 'Status' ? 'bg-emerald-200' : 'bg-slate-200/60'" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3 — Reports (image-style) -->
                    <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm">
                        <div class="absolute inset-0 bg-gradient-to-br from-slate-800/5 to-slate-800/20" />
                        <div class="relative p-8">
                            <h3 class="text-xl font-bold text-slate-900">تقارير مالية لحظية</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">اتخذ قرارات بناءً على بيانات حقيقية. رسوم بيانية تفاعلية لقياس أداء مؤسستك في آنٍ.</p>
                        </div>
                        <!-- Mini chart -->
                        <div class="px-8 pb-8">
                            <div class="flex items-end justify-between gap-1 h-16">
                                <div v-for="(h, i) in [30,55,40,70,50,85,65]" :key="i"
                                    class="flex-1 rounded-t"
                                    :class="i === 5 ? 'bg-emerald-500' : 'bg-slate-200'"
                                    :style="`height: ${h}%`"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Card 4 — Cheques -->
                    <div class="rounded-3xl border border-slate-100 bg-slate-800 p-8 text-white">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10">
                            <svg class="h-6 w-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-xl font-bold">إدارة الشيكات</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">نظم شيكاتك، تتبع تواريخ استحقاق الفواتير الواردة والصادرة.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ── Stats ── -->
        <section class="px-6 py-16">
            <div class="mx-auto max-w-7xl">
                <div class="grid grid-cols-2 gap-6 rounded-3xl border border-slate-100 bg-white p-10 shadow-sm md:grid-cols-4">
                    <div v-for="stat in [
                        { value: '+1M', label: 'فاتورة تم إصدارها' },
                        { value: '24/7', label: 'دعم متخصص' },
                        { value: '99.9%', label: 'وقت الاستجابة' },
                        { value: '+10K', label: 'مؤسسة نشطة' },
                    ]" :key="stat.value" class="text-center">
                        <div class="text-3xl font-black text-emerald-600 sm:text-4xl">{{ stat.value }}</div>
                        <div class="mt-1.5 text-sm text-slate-500">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Bottom CTA ── -->
        <section class="px-6 py-16">
            <div class="mx-auto max-w-7xl">
                <div class="overflow-hidden rounded-3xl bg-slate-900 px-8 py-16 text-center text-white sm:px-16">
                    <h2 class="text-3xl font-black sm:text-4xl">جاهز لنقل أعمالك للمستوى التالي؟</h2>
                    <p class="mx-auto mt-4 max-w-lg text-base text-slate-400">ابدأ رحلتك اليوم مع "نمين" واستمتع بتجربة إدارة لا مثيل لها. سجل الآن واحصل على 14 يوم تجربة مجانية.</p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-4">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-xl bg-emerald-500 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                        >
                            أنشئ مؤسستك الآن
                        </Link>
                        <button class="rounded-xl border border-white/20 px-8 py-3.5 text-base font-semibold text-white transition hover:border-white/40 hover:bg-white/5">
                            تحدث مع الخبراء
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Footer ── -->
        <footer class="border-t border-slate-100 bg-white px-6 py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
                    <div class="flex items-center gap-2.5">
                        <ApplicationLogo class="h-7 w-7 object-contain" />
                        <span class="font-bold text-slate-800">نمين</span>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-6 text-sm text-slate-500">
                        <a href="#" class="transition hover:text-slate-800">الشروط والأحكام</a>
                        <a href="#" class="transition hover:text-slate-800">سياسة الخصوصية</a>
                        <a href="#" class="transition hover:text-slate-800">تواصل معنا</a>
                    </div>
                </div>
                <div class="mt-8 border-t border-slate-100 pt-6 text-center text-xs text-slate-400">
                    © {{ new Date().getFullYear() }} نمين. جميع الحقوق محفوظة لمنصة إدارة الأعمال للمؤسسات الناشئة والمتوسطة.
                </div>
            </div>
        </footer>

    </div>
</template>
