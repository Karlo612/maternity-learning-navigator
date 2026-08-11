import catalog from './interface-catalog.json';

export type Locale = 'en' | 'ckb';

export const categoryOrder = [
    'antenatal-appointments',
    'birth-place-choices',
    'labour-preparation',
    'pain-relief-information',
    'after-birth-postnatal',
    'feeding-support',
] as const;

export type CategorySlug = typeof categoryOrder[number];

export type Messages = {
    languageName: string;
    direction: 'ltr' | 'rtl';
    reviewState: string;
    nav: Record<'navigator' | 'sources' | 'how' | 'evidence' | 'api', string>;
    status: string;
    eyebrow: string;
    heroTitle: string;
    heroCopy: string;
    noDiagnosis: string;
    noStorage: string;
    provenance: string;
    demoTitle: string;
    curatedDemo: string;
    reviewOpenTitle: string;
    reviewOpenBody: string;
    currentState: string;
    demoCopy: string;
    chooseSample: string;
    runDemo: string;
    runningDemo: string;
    freeTextTitle: string;
    freeTextCopy: string;
    freeTextPlaceholder: string;
    resultTitle: string;
    confidence: string;
    sourcesTitle: string;
    whyMatch: string;
    loadingExplanation: string;
    explanationTitle: string;
    supporting: string;
    opposing: string;
    explanationDisclaimer: string;
    demoNotice: string;
    offline: string;
    requestId: string;
    modelVersion: string;
    openOriginal: string;
    categories: Record<CategorySlug, { label: string; description: string }>;
};

export const messages = catalog as Record<Locale, Messages>;
