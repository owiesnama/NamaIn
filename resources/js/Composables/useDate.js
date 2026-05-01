import { usePage } from '@inertiajs/vue3';

const FORMAT_MAP = {
    'DD/MM/YY': { day: '2-digit', month: '2-digit', year: '2-digit' },
    'MM/DD/YY': { month: '2-digit', day: '2-digit', year: '2-digit' },
    'YY/MM/DD': { year: '2-digit', month: '2-digit', day: '2-digit' },
    'Month D, Y': { month: 'long', day: 'numeric', year: 'numeric' },
};

const TIMEZONE_MAP = {
    '+0:00': 'UTC',
    '+1:00': 'Africa/Algiers',
    '+2:00': 'Africa/Khartoum',
    '+3:00': 'Asia/Riyadh',
    '+4:00': 'Asia/Dubai',
};

export function useDate() {
    const page = usePage();

    const resolvePreference = (key, fallback) => {
        const userPrefs = page.props.userPreferences ?? {};
        const tenantPrefs = page.props.preferences ?? {};
        return userPrefs[key] ?? tenantPrefs[key] ?? fallback;
    };

    const getLocale = () => {
        return page.props.locale === 'ar' ? 'ar-SA' : 'en-US';
    };

    const getTimeZone = () => {
        const tz = resolvePreference('timezone', '+2:00');
        return TIMEZONE_MAP[tz] ?? 'Africa/Khartoum';
    };

    const getFormatOptions = () => {
        const format = resolvePreference('dateFormat', 'DD/MM/YY');
        return FORMAT_MAP[format] ?? FORMAT_MAP['DD/MM/YY'];
    };

    const formatDate = (dateString) => {
        if (!dateString) return '—';

        try {
            return new Intl.DateTimeFormat(getLocale(), {
                ...getFormatOptions(),
                timeZone: getTimeZone(),
            }).format(new Date(dateString));
        } catch {
            return dateString;
        }
    };

    const formatDateTime = (dateString) => {
        if (!dateString) return '—';

        try {
            return new Intl.DateTimeFormat(getLocale(), {
                ...getFormatOptions(),
                hour: '2-digit',
                minute: '2-digit',
                timeZone: getTimeZone(),
            }).format(new Date(dateString));
        } catch {
            return dateString;
        }
    };

    return {
        formatDate,
        formatDateTime,
    };
}
