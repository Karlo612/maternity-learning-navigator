import { CSSProperties, useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    ArrowRight, BookOpen, Braces, Check, CheckCircle2, Clipboard, Clock3, Code2,
    Database, ExternalLink, FileCheck2, GitBranch, HeartHandshake, Languages,
    LockKeyhole, Network, Play, Route, ShieldCheck, Sparkles, WifiOff,
} from 'lucide-react';
import { CategorySlug, Locale, categoryOrder, messages } from '../i18n';

type Source = {
    id: string;
    organisation: string;
    title: string;
    url: string;
    language: string;
    category?: string;
    reuse_status?: string;
    last_verified: string;
    fallback_used?: boolean;
    availability_note?: string;
};
type DemoSample = {
    sample_id: string;
    question: string;
    category: { slug: CategorySlug; label: string };
    content_checksum: string;
};
type DemoClassification = {
    request_id: string;
    demo_only: true;
    status: string;
    reason?: string;
    message?: string;
    sample?: { sample_id: string; question: string };
    category?: { slug: CategorySlug; label: string };
    routing_confidence?: number;
    confidence_band?: string;
    model?: { id: string; version: string };
    resources?: Source[];
    explanation_available?: boolean;
};
type Feature = {
    token: string;
    weight: number;
    direction: 'supporting' | 'opposing';
    occurrences: { start: number; end: number }[];
};
type Explanation = {
    request_id: string;
    demo_only: true;
    method: 'LIME';
    sample: { sample_id: string; question: string };
    predicted_class: CategorySlug;
    explained_class: CategorySlug;
    probability: number;
    model: { id: string; version: string };
    sampling: { random_seed: number; num_samples: number; max_features: number };
    features: Feature[];
    disclaimer: string;
};
type ConsoleResult = { request: string; status: number; elapsed: number; response: unknown };

const navigation = [
    ['/', 'navigator'], ['/sources', 'sources'], ['/how-it-works', 'how'],
    ['/evidence', 'evidence'], ['/api-docs', 'api'],
] as const;
const repo = 'https://github.com/Karlo612/maternity-learning-navigator';

async function jsonRequest(url: string, options?: RequestInit) {
    const started = performance.now();
    const response = await fetch(url, {
        headers: { Accept: 'application/json', ...(options?.body ? { 'Content-Type': 'application/json' } : {}) },
        ...options,
    });
    const body = await response.json();
    return { response, body, elapsed: Math.round(performance.now() - started) };
}

function AppHeader({ locale, setLocale }: { locale: Locale; setLocale: (value: Locale) => void }) {
    const copy = messages[locale];
    const path = window.location.pathname;
    return <>
        <div className="status-strip"><div className="container"><span className="status-dot" />{copy.status}</div></div>
        <header className="site-header"><div className="container nav-row">
            <a className="brand" href="/"><span className="brand-mark"><Route size={19} /></span><span>Maternity Learning Navigator</span></a>
            <nav className="nav-links" aria-label="Primary navigation">{navigation.map(([href, key]) =>
                <a className="nav-link" aria-current={path === href ? 'page' : undefined} href={href} key={href}>{copy.nav[key]}</a>,
            )}</nav>
            <label className="sr-only" htmlFor="language">Interface language</label>
            <select id="language" className="language-select" value={locale} onChange={event => setLocale(event.target.value as Locale)}>
                <option value="en">English</option><option value="ckb">کوردی سۆرانی · {messages.ckb.reviewState}</option>
            </select>
        </div></header>
    </>;
}

function ExplanationView({ explanation, locale }: { explanation: Explanation; locale: Locale }) {
    const copy = messages[locale];
    const marks = explanation.features.flatMap(feature => feature.occurrences.map(occurrence => ({ ...occurrence, feature })))
        .sort((a, b) => a.start - b.start || b.end - a.end)
        .filter((mark, index, items) => index === 0 || mark.start >= items[index - 1].end);
    const fragments: React.ReactNode[] = [];
    let cursor = 0;
    marks.forEach((mark, index) => {
        if (mark.start > cursor) fragments.push(explanation.sample.question.slice(cursor, mark.start));
        const strength = Math.min(1, Math.abs(mark.feature.weight) * 3.5);
        fragments.push(<mark
            className={`lime-token ${mark.feature.direction}`}
            style={{ '--strength': strength } as CSSProperties}
            title={`${mark.feature.direction}: ${mark.feature.weight.toFixed(4)}`}
            aria-label={`${explanation.sample.question.slice(mark.start, mark.end)}, ${mark.feature.direction}, ${mark.feature.weight.toFixed(4)}`}
            key={`${mark.start}-${mark.end}-${index}`}
        >{explanation.sample.question.slice(mark.start, mark.end)}</mark>);
        cursor = mark.end;
    });
    fragments.push(explanation.sample.question.slice(cursor));
    const ordered = [...explanation.features].sort((left, right) =>
        (left.occurrences[0]?.start ?? Number.MAX_SAFE_INTEGER) - (right.occurrences[0]?.start ?? Number.MAX_SAFE_INTEGER),
    );

    return <section className="explanation-panel" aria-labelledby="lime-title">
        <div className="result-heading"><div><span className="eyebrow">Local explanation</span><h3 id="lime-title">{copy.explanationTitle}</h3></div><span className="method-pill">LIME · seed {explanation.sampling.random_seed}</span></div>
        <p className="explained-sentence" dir={copy.direction}>{fragments}</p>
        <div className="feature-list">{ordered.map(feature => <div className="feature-row" key={`${feature.token}-${feature.weight}`}>
            <span className={`direction-icon ${feature.direction}`} aria-hidden="true">{feature.direction === 'supporting' ? '+' : '−'}</span>
            <span className="feature-token" dir={copy.direction}>{feature.token}</span>
            <span>{feature.direction === 'supporting' ? copy.supporting : copy.opposing}</span>
            <code>{feature.weight > 0 ? '+' : ''}{feature.weight.toFixed(4)}</code>
        </div>)}</div>
        <p className="lime-disclaimer"><ShieldCheck size={17} />{copy.explanationDisclaimer}</p>
        <p className="sampling-note">Predicted and explained class: <code>{explanation.predicted_class}</code> · {explanation.sampling.num_samples.toLocaleString()} perturbations · output discarded after this response.</p>
    </section>;
}

function DemoPanel({ locale, online }: { locale: Locale; online: boolean }) {
    const copy = messages[locale];
    const [samples, setSamples] = useState<DemoSample[]>([]);
    const [reviewState, setReviewState] = useState('loading');
    const [selected, setSelected] = useState('');
    const [result, setResult] = useState<DemoClassification | null>(null);
    const [explanation, setExplanation] = useState<Explanation | null>(null);
    const [busy, setBusy] = useState(false);
    const [explaining, setExplaining] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setResult(null); setExplanation(null); setError(''); setSelected('');
        jsonRequest(`/api/v1/demo/samples?locale=${locale}`).then(({ body }) => {
            const data = (body.data ?? []) as DemoSample[];
            setSamples(data); setReviewState(body.review_status ?? 'missing'); setSelected(data[0]?.sample_id ?? '');
        }).catch(() => { setSamples([]); setReviewState('unavailable'); });
    }, [locale]);

    const run = async () => {
        if (!selected) return;
        setBusy(true); setResult(null); setExplanation(null); setError('');
        try {
            const { response, body } = await jsonRequest('/api/v1/demo/classifications', {
                method: 'POST', body: JSON.stringify({ sample_id: selected }),
            });
            setResult(body as DemoClassification);
            if (!response.ok) setError(body.message ?? 'The fixed demonstration could not be run.');
        } catch { setError('The demonstration service is unavailable.'); }
        finally { setBusy(false); }
    };
    const explain = async () => {
        if (!result?.request_id) return;
        setExplaining(true); setError('');
        try {
            const { response, body } = await jsonRequest(`/api/v1/demo/classifications/${result.request_id}/explanation`, { method: 'POST' });
            if (response.ok) setExplanation(body as Explanation); else setError(body.message ?? 'The explanation was not released.');
        } catch { setError('The explanation service is unavailable.'); }
        finally { setExplaining(false); }
    };

    return <div className="router-card">
        <div className="demo-kicker"><span><CheckCircle2 size={15} />{copy.curatedDemo}</span><span>{copy.reviewState}</span></div>
        <h2>{copy.demoTitle}</h2><p>{copy.demoCopy}</p>
        {samples.length > 0 ? <>
            <fieldset className="sample-list"><legend>{copy.chooseSample}</legend>{samples.map(sample =>
                <label className={`sample-option ${selected === sample.sample_id ? 'selected' : ''}`} key={sample.sample_id}>
                    <input type="radio" name="demo-sample" value={sample.sample_id} checked={selected === sample.sample_id} onChange={() => setSelected(sample.sample_id)} />
                    <span><strong>{copy.categories[sample.category.slug].label}</strong><span dir={copy.direction}>{sample.question}</span></span>
                </label>,
            )}</fieldset>
            <button className="primary-button" type="button" onClick={run} disabled={!online || busy || !selected}>{busy ? copy.runningDemo : <>{copy.runDemo}<ArrowRight size={17} /></>}</button>
        </> : <div className="gate-note"><Languages size={18} /><span><strong>{copy.reviewOpenTitle}</strong><br />{copy.reviewOpenBody} {copy.currentState}: <code>{reviewState}</code>.</span></div>}
        {!online && <div className="offline-note"><WifiOff size={16} />{copy.offline}</div>}
        {error && <p className="notice" role="alert">{error}</p>}
        {result?.status === 'matched' && <section className="result-panel" aria-live="polite">
            <div className="result-heading"><div><span className="eyebrow">{copy.resultTitle}</span><h3>{result.category?.label}</h3></div><span className="confidence-pill">{copy.confidence}: {result.confidence_band} · {Math.round((result.routing_confidence ?? 0) * 100)}%</span></div>
            <dl className="request-meta"><div><dt>{copy.requestId}</dt><dd><code>{result.request_id}</code></dd></div><div><dt>{copy.modelVersion}</dt><dd><code>{result.model?.id}@{result.model?.version}</code></dd></div></dl>
            <h4>{copy.sourcesTitle}</h4>{result.resources?.map(source => <a className="resource-mini" href={source.url} target="_blank" rel="noreferrer" key={source.id}>{source.title}<span>{source.organisation} · {source.language}{source.fallback_used ? ' · English-only source' : ''}</span></a>)}
            <button className="secondary-button explain-button" type="button" onClick={explain} disabled={explaining}>{explaining ? copy.loadingExplanation : <><Sparkles size={16} />{copy.whyMatch}</>}</button>
        </section>}
        {explanation && <ExplanationView explanation={explanation} locale={locale} />}
        <div className="locked-free-text"><LockKeyhole size={18} /><div><strong>{copy.freeTextTitle}</strong><p>{copy.freeTextCopy}</p><textarea disabled aria-label={copy.freeTextTitle} placeholder={copy.freeTextPlaceholder} /></div></div>
        <p className="demo-notice">{copy.demoNotice}</p>
    </div>;
}

function Home({ locale, online }: { locale: Locale; online: boolean }) {
    const copy = messages[locale];
    return <main><div className="container hero"><div><span className="eyebrow"><Sparkles size={15} />{copy.eyebrow}</span><h1>{copy.heroTitle}</h1><p className="hero-copy">{copy.heroCopy}</p><div className="trust-row"><span className="trust-chip"><ShieldCheck size={15} />{copy.noDiagnosis}</span><span className="trust-chip"><Database size={15} />{copy.noStorage}</span><span className="trust-chip"><FileCheck2 size={15} />{copy.provenance}</span></div></div><DemoPanel locale={locale} online={online} /></div>
        <section className="section"><div className="container"><div className="section-heading"><div><span className="eyebrow">Six bounded outputs</span><h2>Educational topics, never clinical answers</h2></div><p>Each result resolves through MySQL to registered source metadata. Sorani questions transparently link to English-only sources where no reviewed Sorani equivalent is registered.</p></div><div className="category-grid">{categoryOrder.map((slug, index) => <a className="category-card" href={`/sources?category=${slug}&locale=${locale}`} key={slug}><span className="category-number">0{index + 1}</span><h3>{copy.categories[slug].label}</h3><p>{copy.categories[slug].description}</p><span className="source-link">{copy.openOriginal} →</span></a>)}</div></div></section>
        <section className="section"><div className="container"><div className="section-heading"><div><span className="eyebrow">Recruiter evidence</span><h2>One visible, testable request path</h2></div><p>The demonstration exercises real PHP, MySQL, private Python inference and predicted-class LIME without accepting unrestricted maternity text.</p></div><div className="evidence-grid"><div className="evidence-card"><Code2 /><h3>Laravel public boundary</h3><p>Sample IDs enter a versioned REST controller; GraphQL remains read-only.</p></div><div className="evidence-card"><Network /><h3>FastAPI model boundary</h3><p>A private authenticated service loads only a checksum-matched curated-demo artifact.</p></div><div className="evidence-card"><Sparkles /><h3>Correct local explanation</h3><p>LIME receives the actual predicted class, fixed seed 41 and returns transient source spans.</p></div></div></div></section>
    </main>;
}

function Sources({ locale }: { locale: Locale }) {
    const [sources, setSources] = useState<Source[]>([]);
    const category = new URLSearchParams(window.location.search).get('category');
    useEffect(() => { jsonRequest(`/api/v1/resources?locale=${locale}${category ? `&category=${encodeURIComponent(category)}` : ''}`).then(({ body }) => setSources(body.data ?? [])); }, [category, locale]);
    return <main className="container"><div className="page-hero"><span className="eyebrow"><BookOpen size={15} />Provenance first</span><h1>Registered source directory</h1><p>Links, language, use status and verification dates are shown without reproducing clinical prose or implying unavailable translations.</p></div><div className="source-grid">{sources.map(source => <article className="source-card" key={source.id}><span className="org">{source.organisation}</span><h3>{source.title}</h3><div className="source-meta"><span className="tag">{source.language}</span>{source.reuse_status && <span className="tag">{source.reuse_status.replaceAll('_', ' ')}</span>}<span className="tag">Checked {source.last_verified}</span></div>{source.fallback_used && <p className="availability-note">{source.availability_note}</p>}<a className="source-link" href={source.url} target="_blank" rel="noreferrer">Open original <ExternalLink size={13} /></a></article>)}</div></main>;
}

function Evidence() {
    const links = [
        ['PHP demo controller', 'app/app/Http/Controllers/Api/DemoClassificationController.php'],
        ['FastAPI demo route', 'ml-service/app/main.py'],
        ['Predicted-class LIME', 'ml-service/app/explanations.py'],
        ['GraphQL schema', 'app/graphql/schema.graphql'],
        ['MySQL migration', 'app/database/migrations/2026_08_11_000002_add_curated_demo_tables.php'],
        ['Governance validator', 'scripts/validate_resources.py'],
    ];
    return <main className="container"><div className="page-hero"><span className="eyebrow"><FileCheck2 size={15} />Evidence before claims</span><h1>Technical and governance evidence</h1><p>The curated route demonstrates the architecture. It does not establish general model accuracy, clinical validity or approval.</p></div>
        <div className="evidence-grid"><div className="evidence-card"><div className="metric">144</div><div className="metric-label">Governed draft samples</div></div><div className="evidence-card"><div className="metric">12 + 12</div><div className="metric-label">Visible + hidden fixtures</div></div><div className="evidence-card"><div className="metric">0</div><div className="metric-label">Clinical claims</div></div></div>
        <section className="section two-column"><div className="content-card"><h2>What fixture checks prove</h2><p>After exact-text approval, all fixed visible and hidden examples must return their reviewed category. That proves the deployed path behaves as specified for those fixtures only.</p><p className="notice">No macro-F1, calibration or maternity-domain performance claim is made from this small corpus.</p></div><div className="locked-panel"><h3><LockKeyhole size={19} />Production route still locked</h3><p>The 600-row bilingual training set, 120 evaluation fixtures, independent clinical-safety review and XLM-R comparison remain follow-on gates.</p></div></section>
        <section className="section"><div className="section-heading"><div><span className="eyebrow">Inspect the implementation</span><h2>Evidence linked to code</h2></div></div><div className="source-grid">{links.map(([label, path]) => <a className="evidence-code-link" href={`${repo}/blob/main/${path}`} target="_blank" rel="noreferrer" key={path}><Code2 size={18} /><span><strong>{label}</strong><code>{path}</code></span><ExternalLink size={15} /></a>)}</div></section>
        <section className="section"><div className="section-heading"><div><span className="eyebrow">Vacancy alignment</span><h2>PHP, APIs and responsible XAI</h2></div></div><div className="evidence-grid">{[
            ['PHP · MySQL', 'Laravel 13 owns validation, sample governance, telemetry and source relationships in MySQL 8.4.'],
            ['REST · GraphQL', 'Versioned write contracts are separate from a depth-limited, read-only GraphQL source API.'],
            ['Python · deployment', 'FastAPI loads mode-isolated, checksum-verified scikit-learn artifacts in a private container.'],
            ['LIME', 'The explanation service explicitly explains the predicted index and returns reproducible signed source spans.'],
            ['Privacy', 'Only derived events and a governed sample reference persist; explanation features are response-only.'],
            ['Multilingual', 'The shared catalogue drives English/Sorani text and full document RTL, behind checksum approval.'],
        ].map(([title, body]) => <div className="evidence-card" key={title}><CheckCircle2 size={18} /><h3>{title}</h3><p>{body}</p></div>)}</div></section>
    </main>;
}

function HowItWorks() {
    return <main className="container"><div className="page-hero"><span className="eyebrow"><Network size={15} />Bounded by design</span><h1>Follow one request end to end</h1><p>The curated and production registries are deliberately separate: a portfolio fixture can never unlock public free text.</p></div><div className="flow-strip"><div><span>1</span><strong>React sends sample ID</strong><p>No browser-supplied question enters the demo contract.</p></div><ArrowRight /><div><span>2</span><strong>Laravel retrieves approved text</strong><p>MySQL provides category, locale and source provenance.</p></div><ArrowRight /><div><span>3</span><strong>FastAPI routes</strong><p>The private curated registry verifies mode and artifact checksum.</p></div><ArrowRight /><div><span>4</span><strong>LIME explains</strong><p>Predicted-class weights return through PHP and are discarded.</p></div></div><section className="section"><div className="two-column"><div className="content-card"><h2>Persisted</h2><ul><li>Random request ID and curated-demo mode</li><li>Governed sample, category and model references</li><li>Confidence band and latency</li></ul></div><div className="content-card"><h2>Never persisted</h2><ul><li>Arbitrary user maternity text</li><li>LIME tokens or occurrence spans</li><li>Clinical symptoms or personal data</li><li>Free-text feedback</li></ul></div></div></section></main>;
}

const graphQueries = {
    categories: '{ categories(locale: "en") { slug label description } }',
    resources: '{ resources(category: "antenatal-appointments", locale: "ckb") { code title language requestedLocale fallbackUsed } }',
    source: '{ source(code: "NHS-006") { code organisation title url lastVerified } }',
    modelVersion: '{ modelVersion { modelId version role status servingDefault limitations } }',
};

function ApiDocs() {
    const [result, setResult] = useState<ConsoleResult | null>(null);
    const [busy, setBusy] = useState('');
    const [sample, setSample] = useState<DemoSample | null>(null);
    const [requestId, setRequestId] = useState('');
    const [snippet, setSnippet] = useState<'curl' | 'javascript' | 'php'>('curl');
    const [copied, setCopied] = useState(false);
    useEffect(() => { jsonRequest('/api/v1/demo/samples?locale=en').then(({ body }) => setSample(body.data?.[0] ?? null)); }, []);

    const execute = async (name: string, url: string, method = 'GET', body?: unknown) => {
        setBusy(name);
        try {
            const payload = body === undefined ? undefined : JSON.stringify(body);
            const call = await jsonRequest(url, { method, body: payload });
            setResult({ request: `${method} ${url}${payload ? `\n\n${JSON.stringify(body, null, 2)}` : ''}`, status: call.response.status, elapsed: call.elapsed, response: call.body });
            if (name === 'demo-classification' && call.response.ok) setRequestId(call.body.request_id);
        } catch (error) {
            setResult({ request: `${method} ${url}`, status: 0, elapsed: 0, response: { error: String(error) } });
        } finally { setBusy(''); }
    };
    const runGraphql = (name: keyof typeof graphQueries) => execute(`graphql-${name}`, '/graphql', 'POST', { query: graphQueries[name] });
    const currentRequest = result?.request ?? 'GET /api/v1/health';
    const [firstLine, ...bodyLines] = currentRequest.split('\n');
    const [method, url] = firstLine.split(' ');
    const bodyText = bodyLines.join('\n').trim();
    const snippets = {
        curl: `curl -sS -X ${method} '${window.location.origin}${url}' -H 'Accept: application/json'${bodyText ? ` \\\n+  -H 'Content-Type: application/json' \\\n+  --data '${bodyText.replaceAll("'", "'\\''")}'` : ''}`,
        javascript: `const response = await fetch('${url}', {\n  method: '${method}',\n  headers: { Accept: 'application/json'${bodyText ? ", 'Content-Type': 'application/json'" : ''} }${bodyText ? `,\n  body: JSON.stringify(${bodyText})` : ''}\n});\nconsole.log(await response.json());`,
        php: `use Illuminate\\Support\\Facades\\Http;\n\n$response = Http::acceptJson()${bodyText ? `->${method.toLowerCase()}('${url}', ${bodyText})` : `->${method.toLowerCase()}('${url}')`};\n$response->throw()->json();`,
    };
    const copySnippet = async () => { await navigator.clipboard.writeText(snippets[snippet]); setCopied(true); setTimeout(() => setCopied(false), 1200); };

    return <main className="container"><div className="page-hero"><span className="eyebrow"><Braces size={15} />Live engineering exhibit</span><h1>Execute REST and GraphQL here</h1><p>Every control below calls the running application. Inspect the request, status, timing and formatted response without installing an API client.</p></div>
        <div className="api-layout"><aside className="api-controls"><h2>REST</h2>{[
            ['health', 'GET', '/api/v1/health', undefined],
            ['resources', 'GET', '/api/v1/resources?category=antenatal-appointments&locale=ckb', undefined],
            ['model-card', 'GET', '/api/v1/model-card', undefined],
            ['demo-samples', 'GET', '/api/v1/demo/samples?locale=en', undefined],
        ].map(([name, verb, path, body]) => <button type="button" onClick={() => execute(String(name), String(path), String(verb), body)} disabled={!!busy} key={String(name)}><span className="verb">{verb as string}</span>{String(name).replaceAll('-', ' ')}<Play size={14} /></button>)}
            <button type="button" onClick={() => sample && execute('demo-classification', '/api/v1/demo/classifications', 'POST', { sample_id: sample.sample_id })} disabled={!!busy || !sample}><span className="verb post">POST</span>demo classification<Play size={14} /></button>
            <button type="button" onClick={() => execute('demo-explanation', `/api/v1/demo/classifications/${requestId}/explanation`, 'POST')} disabled={!!busy || !requestId}><span className="verb post">POST</span>demo explanation<Play size={14} /></button>
            <h2>GraphQL</h2>{Object.keys(graphQueries).map(name => <button type="button" onClick={() => runGraphql(name as keyof typeof graphQueries)} disabled={!!busy} key={name}><span className="verb gql">GQL</span>{name}<Play size={14} /></button>)}
        </aside><section className="console-panel" aria-live="polite"><div className="console-toolbar"><span><span className={`http-status ${(result?.status ?? 0) < 400 ? 'ok' : 'bad'}`}>{result?.status || '—'}</span> HTTP status</span><span><Clock3 size={14} />{result?.elapsed ?? '—'} ms</span></div><h2>Request</h2><pre className="code-block">{result?.request ?? 'Choose an operation to send a live request.'}</pre><h2>Response</h2><pre className="code-block response-code">{result ? JSON.stringify(result.response, null, 2) : '{\n  "waiting": true\n}'}</pre></section></div>
        <section className="section"><div className="snippet-card"><div className="snippet-toolbar"><div className="snippet-tabs" role="tablist">{(['curl', 'javascript', 'php'] as const).map(value => <button role="tab" aria-selected={snippet === value} type="button" onClick={() => setSnippet(value)} key={value}>{value === 'php' ? 'Laravel / PHP' : value}</button>)}</div><button className="copy-button" type="button" onClick={copySnippet}>{copied ? <Check size={15} /> : <Clipboard size={15} />}{copied ? 'Copied' : 'Copy'}</button></div><pre className="code-block">{snippets[snippet]}</pre></div><p><a className="secondary-button" href="/api/v1/openapi.json">Open OpenAPI 3.1 document <ExternalLink size={15} /></a></p></section>
    </main>;
}

function PolicyPage({ type }: { type: 'privacy' | 'accessibility' }) {
    const privacy = type === 'privacy';
    return <main className="container"><div className="page-hero"><span className="eyebrow">{privacy ? <ShieldCheck size={15} /> : <HeartHandshake size={15} />}{privacy ? 'Data minimisation' : 'Inclusive by default'}</span><h1>{privacy ? 'Privacy and intended use' : 'Accessibility statement'}</h1><p>{privacy ? 'The fixed demo stores only derived telemetry and a reference to governed application content.' : 'The interface targets WCAG 2.2 AA and includes explicit testing for RTL and bidirectional text.'}</p></div><div className="two-column">{privacy ? <><div className="content-card"><h2>What may be stored</h2><ul><li>Random request ID and curated-demo mode</li><li>Governed sample, category and model references</li><li>Confidence band, locale and latency</li><li>Fixed-choice helpfulness feedback</li></ul></div><div className="content-card"><h2>What is not stored</h2><ul><li>Arbitrary user questions or LIME tokens</li><li>Names, contact details or symptoms</li><li>Patient records or special-category datasets</li><li>Free-text feedback</li></ul></div></> : <><div className="content-card"><h2>Implemented</h2><ul><li>Keyboard-visible focus and semantic controls</li><li>Reduced-motion support and responsive zoom</li><li>Text plus symbols for LIME direction</li><li>Full-page RTL and original-order Sorani spans</li></ul></div><div className="content-card"><h2>Release gate</h2><p>Automated checks are not certification. Manual keyboard, zoom, contrast, screen-reader and Sorani bidirectional-text checks are required before public indexing.</p></div></>}</div></main>;
}

function Footer() {
    return <footer className="site-footer"><div className="container footer-row"><div><strong>Maternity Learning Navigator</strong><br />Independent portfolio demonstration by Karlo Nahro.<br />Not affiliated with or endorsed by the NHS or The Real Birth Company.</div><div className="footer-links"><a href="/privacy">Privacy</a><a href="/accessibility">Accessibility</a><a href="/evidence">Evidence</a><a href={repo} target="_blank" rel="noreferrer"><GitBranch size={14} />GitHub repository</a></div></div></footer>;
}

export default function Navigator() {
    const [locale, setLocale] = useState<Locale>(() => (localStorage.getItem('navigator-locale') === 'ckb' ? 'ckb' : 'en'));
    const [online, setOnline] = useState(navigator.onLine);
    useEffect(() => {
        localStorage.setItem('navigator-locale', locale);
        document.documentElement.lang = locale;
        document.documentElement.dir = messages[locale].direction;
    }, [locale]);
    useEffect(() => {
        const on = () => setOnline(true); const off = () => setOnline(false);
        addEventListener('online', on); addEventListener('offline', off);
        return () => { removeEventListener('online', on); removeEventListener('offline', off); };
    }, []);
    const path = window.location.pathname;
    const content = useMemo(() => path === '/sources' ? <Sources locale={locale} />
        : path === '/evidence' ? <Evidence />
            : path === '/how-it-works' ? <HowItWorks />
                : path === '/api-docs' ? <ApiDocs />
                    : path === '/privacy' ? <PolicyPage type="privacy" />
                        : path === '/accessibility' ? <PolicyPage type="accessibility" />
                            : <Home locale={locale} online={online} />, [path, locale, online]);
    return <div className="app-shell" dir={messages[locale].direction}><Head title="Maternity Learning Navigator" /><AppHeader locale={locale} setLocale={setLocale} />{content}<Footer /></div>;
}
