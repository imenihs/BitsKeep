const PREFIX_FACTORS = {
    Y: 1e24,
    Z: 1e21,
    E: 1e18,
    P: 1e15,
    T: 1e12,
    G: 1e9,
    M: 1e6,
    k: 1e3,
    '': 1,
    m: 1e-3,
    u: 1e-6,
    'µ': 1e-6,
    'μ': 1e-6,
    n: 1e-9,
    p: 1e-12,
    f: 1e-15,
};

const ENGINEERING_VALUE_PREFIX_FACTORS = {
    ...PREFIX_FACTORS,
    K: 1e3,
};

const HUMAN_PREFIX_ORDER = ['Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];
const RANGE_SPLIT_PATTERN = /\s*(?:〜|~|～|to)\s*/iu;
const TRIPLE_SPLIT_PATTERN = /\s*(?:\/|／|\|)\s*/u;
const VALID_PROFILES = ['typ', 'range', 'max_only', 'min_only', 'triple'];
const PROFILE_ALIASES = {
    single: 'typ',
    range: 'range',
    max: 'max_only',
    min: 'min_only',
    triple: 'triple',
};

export const SPEC_PROFILE_OPTIONS = [
    { value: 'typ', label: 'typ' },
    { value: 'range', label: '範囲' },
    { value: 'max_only', label: '最大' },
    { value: 'min_only', label: '最小' },
    { value: 'triple', label: '3値' },
];

export const createEmptySpecRow = () => ({
    spec_type_id: '',
    value_profile: 'typ',
    value: '',
    value_typ: '',
    value_min: '',
    value_max: '',
    unit: '',
    value_numeric_typ: '',
    value_numeric_min: '',
    value_numeric_max: '',
    normalized_unit: '',
});

export const normalizeSpecProfile = (profile) => {
    const normalized = String(profile ?? '').trim();
    if (VALID_PROFILES.includes(normalized)) {
        return normalized;
    }

    return PROFILE_ALIASES[normalized] ?? 'typ';
};

export const getSpecProfileLabel = (profile) => (
    SPEC_PROFILE_OPTIONS.find((item) => item.value === normalizeSpecProfile(profile))?.label ?? 'typ'
);

export const getSpecDisplayName = (specOrProfile, specType = null) => {
    const profile = typeof specOrProfile === 'string'
        ? normalizeSpecProfile(specOrProfile)
        : normalizeSpecProfile(specOrProfile?.value_profile);
    const baseName = typeof specType === 'string'
        ? specType
        : specType?.name
            ?? specOrProfile?.spec_type?.name
            ?? specOrProfile?.specType?.name
            ?? specOrProfile?.spec_type_name
            ?? '';

    if (!baseName) return '';

    if (profile === 'max_only') return `最大${baseName}`;
    if (profile === 'min_only') return `最小${baseName}`;
    if (profile === 'range') return `${baseName}範囲`;

    return baseName;
};

export const buildSpecDraftFromApi = (spec = {}) => {
    const draft = {
        ...createEmptySpecRow(),
        spec_type_id: spec.spec_type_id ?? '',
        unit: normalizeUnitLabel(spec.unit ?? ''),
        value_numeric_typ: spec.value_numeric_typ ?? '',
        value_numeric_min: spec.value_numeric_min ?? '',
        value_numeric_max: spec.value_numeric_max ?? '',
        normalized_unit: spec.normalized_unit ?? '',
    };

    const profile = inferProfile(spec);
    draft.value_profile = profile;

    if (profile === 'typ') {
        draft.value_typ = resolveInputValue(spec, ['value_typ', 'typ', 'value']);
    } else if (profile === 'range') {
        const [valueMin, valueMax] = resolveRangeValues(spec);
        draft.value_min = valueMin;
        draft.value_max = valueMax;
    } else if (profile === 'max_only') {
        draft.value_max = resolveInputValue(spec, ['value_max', 'max', 'value']);
    } else if (profile === 'min_only') {
        draft.value_min = resolveInputValue(spec, ['value_min', 'min', 'value']);
    } else if (profile === 'triple') {
        const [valueMin, valueTyp, valueMax] = resolveTripleValues(spec);
        draft.value_min = valueMin;
        draft.value_typ = valueTyp;
        draft.value_max = valueMax;
    }

    return draft;
};

export const buildSpecPayload = (spec) => {
    const profile = normalizeSpecProfile(spec?.value_profile);
    const payload = {
        spec_type_id: spec?.spec_type_id ?? '',
        value_profile: profile,
        value: '',
        value_typ: '',
        value_min: '',
        value_max: '',
        unit: normalizeUnitLabel(spec?.unit ?? ''),
    };

    if (profile === 'typ') {
        payload.value_typ = cleanText(spec?.value_typ);
        payload.value = payload.value_typ;
    } else if (profile === 'range') {
        payload.value_min = cleanText(spec?.value_min);
        payload.value_max = cleanText(spec?.value_max);
        payload.value = buildRangeLabel(payload.value_min, payload.value_max);
    } else if (profile === 'max_only') {
        payload.value_max = cleanText(spec?.value_max);
        payload.value = payload.value_max;
    } else if (profile === 'min_only') {
        payload.value_min = cleanText(spec?.value_min);
        payload.value = payload.value_min;
    } else if (profile === 'triple') {
        payload.value_min = cleanText(spec?.value_min);
        payload.value_typ = cleanText(spec?.value_typ);
        payload.value_max = cleanText(spec?.value_max);
        payload.value = buildTripleLabel(payload.value_min, payload.value_typ, payload.value_max);
    }

    return payload;
};

export const normalizeSpecDraft = (spec, specType) => {
    const payload = buildSpecPayload(spec);
    const baseUnit = normalizeUnitLabel(specType?.base_unit ?? '');
    let resolvedUnit = payload.unit;

    if (!resolvedUnit) {
        if (payload.value_profile === 'typ') {
            const extracted = extractInlineUnit(payload.value_typ);
            payload.value_typ = extracted.value;
            resolvedUnit = extracted.unit;
        } else if (payload.value_profile === 'max_only') {
            const extracted = extractInlineUnit(payload.value_max);
            payload.value_max = extracted.value;
            resolvedUnit = extracted.unit;
        } else if (payload.value_profile === 'min_only') {
            const extracted = extractInlineUnit(payload.value_min);
            payload.value_min = extracted.value;
            resolvedUnit = extracted.unit;
        }
    }

    const normalizedUnit = baseUnit || resolvedUnit;
    const factor = resolveFactor(specType, resolvedUnit, baseUnit);
    const profileLabel = getSpecProfileLabel(payload.value_profile);

    if (payload.value_profile === 'range') {
        const min = parseEngineeringNumber(payload.value_min);
        const max = parseEngineeringNumber(payload.value_max);
        if (min === null || max === null) {
            return {
                hasNumeric: false,
                profileLabel,
                canonicalText: '',
                recommendedText: `${buildRangeLabel(payload.value_min, payload.value_max)}${resolvedUnit ? ` ${resolvedUnit}` : ''}`.trim(),
            };
        }

        let canonicalMin = min.value * min.factor * factor;
        let canonicalMax = max.value * max.factor * factor;
        if (canonicalMin > canonicalMax) {
            [canonicalMin, canonicalMax] = [canonicalMax, canonicalMin];
        }
        const humanized = humanizeRange(canonicalMin, canonicalMax, normalizedUnit, resolvedUnit);

        return {
            hasNumeric: true,
            profileLabel,
            canonicalText: `min: ${formatScientific(canonicalMin)} ${normalizedUnit} / max: ${formatScientific(canonicalMax)} ${normalizedUnit}`.trim(),
            recommendedText: `${humanized.min} 〜 ${humanized.max} ${humanized.unit}`.trim(),
            recommendedUnit: humanized.unit,
        };
    }

    if (payload.value_profile === 'triple') {
        const min = parseEngineeringNumber(payload.value_min);
        const typ = parseEngineeringNumber(payload.value_typ);
        const max = parseEngineeringNumber(payload.value_max);
        if (min === null || typ === null || max === null) {
            return {
                hasNumeric: false,
                profileLabel,
                canonicalText: '',
                recommendedText: `${buildTripleLabel(payload.value_min, payload.value_typ, payload.value_max)}${resolvedUnit ? ` ${resolvedUnit}` : ''}`.trim(),
            };
        }

        const canonicalMin = min.value * min.factor * factor;
        const canonicalTyp = typ.value * typ.factor * factor;
        const canonicalMax = max.value * max.factor * factor;
        const humanized = humanizeTriple(canonicalMin, canonicalTyp, canonicalMax, normalizedUnit, resolvedUnit);

        return {
            hasNumeric: true,
            profileLabel,
            canonicalText: `min: ${formatScientific(canonicalMin)} ${normalizedUnit} / typ: ${formatScientific(canonicalTyp)} ${normalizedUnit} / max: ${formatScientific(canonicalMax)} ${normalizedUnit}`.trim(),
            recommendedText: `${humanized.min} / ${humanized.typ} / ${humanized.max} ${humanized.unit}`.trim(),
            recommendedUnit: humanized.unit,
        };
    }

    const singleKey = payload.value_profile === 'max_only' ? 'value_max'
        : payload.value_profile === 'min_only' ? 'value_min'
            : 'value_typ';
    const rawValue = payload[singleKey];
    const number = parseEngineeringNumber(rawValue);
    if (number === null) {
        return {
            hasNumeric: false,
            profileLabel,
            canonicalText: '',
            recommendedText: `${rawValue}${resolvedUnit ? ` ${resolvedUnit}` : ''}`.trim(),
        };
    }

    const canonical = number.value * number.factor * factor;
    const humanized = humanizeSingle(canonical, normalizedUnit, resolvedUnit);
    const kindLabel = payload.value_profile === 'max_only'
        ? 'max'
        : payload.value_profile === 'min_only'
            ? 'min'
            : 'typ';

    return {
        hasNumeric: true,
        profileLabel,
        canonicalText: `${kindLabel}: ${formatScientific(canonical)} ${normalizedUnit}`.trim(),
        recommendedText: `${humanized.value} ${humanized.unit}`.trim(),
        recommendedUnit: humanized.unit,
    };
};

export const getSpecUnitSuggestions = (specType) => {
    if (!specType) return [];

    const suggestions = new Set(
        (specType.units ?? [])
            .map((item) => normalizeUnitLabel(item.unit))
            .filter(Boolean),
    );

    const baseUnit = normalizeUnitLabel(specType.base_unit ?? '');
    if (baseUnit && canHumanize(baseUnit)) {
        for (const prefix of ['G', 'M', 'k', '', 'm', 'u', 'n', 'p']) {
            suggestions.add(`${prefix}${baseUnit}`);
        }
    } else if (baseUnit) {
        suggestions.add(baseUnit);
    }

    return [...suggestions];
};

const inferProfile = (spec) => {
    const explicit = normalizeSpecProfile(spec?.value_profile ?? spec?.profile ?? spec?.value_mode ?? '');
    if (spec?.value_profile || spec?.profile || spec?.value_mode) {
        return explicit;
    }

    const hasTyp = hasValue(spec?.value_typ ?? spec?.typ);
    const hasMin = hasValue(spec?.value_min ?? spec?.min);
    const hasMax = hasValue(spec?.value_max ?? spec?.max);

    if (hasMin && hasTyp && hasMax) return 'triple';
    if (hasMin && hasMax) return 'range';
    if (hasMax) return 'max_only';
    if (hasMin) return 'min_only';
    if (hasTyp) return 'typ';

    const value = cleanText(spec?.value);
    if (looksLikeTriple(value)) return 'triple';
    if (looksLikeRange(value)) return 'range';

    return 'typ';
};

const resolveRangeValues = (spec) => {
    const rawMin = cleanText(spec?.value_min ?? spec?.min);
    const rawMax = cleanText(spec?.value_max ?? spec?.max);
    if (rawMin || rawMax) {
        return [rawMin, rawMax];
    }

    return splitRangeValue(spec?.value ?? '');
};

const resolveTripleValues = (spec) => {
    const rawMin = cleanText(spec?.value_min ?? spec?.min);
    const rawTyp = cleanText(spec?.value_typ ?? spec?.typ);
    const rawMax = cleanText(spec?.value_max ?? spec?.max);
    if (rawMin || rawTyp || rawMax) {
        return [rawMin, rawTyp, rawMax];
    }

    return splitTripleValue(spec?.value ?? '');
};

const resolveInputValue = (source, keys) => {
    for (const key of keys) {
        const value = cleanText(source?.[key]);
        if (value) return value;
    }

    return '';
};

const resolveFactor = (specType, unit, baseUnit) => {
    const normalizedUnit = normalizeUnitLabel(unit);
    if (!normalizedUnit) return 1;

    const exact = (specType?.units ?? []).find((item) => normalizeUnitLabel(item.unit) === normalizedUnit);
    if (exact && Number.isFinite(Number(exact.factor))) {
        return Number(exact.factor);
    }

    if (baseUnit && normalizedUnit === baseUnit) {
        return 1;
    }

    if (baseUnit && normalizedUnit.endsWith(baseUnit)) {
        const prefix = normalizedUnit.slice(0, normalizedUnit.length - baseUnit.length);
        if (Object.hasOwn(PREFIX_FACTORS, prefix)) {
            return PREFIX_FACTORS[prefix];
        }
    }

    return 1;
};

const humanizeSingle = (canonicalValue, normalizedUnit, fallbackUnit) => {
    if (!canHumanize(normalizedUnit)) {
        return { value: formatDisplayNumber(canonicalValue), unit: fallbackUnit || normalizedUnit };
    }

    const prefix = choosePrefix(canonicalValue);
    const factor = PREFIX_FACTORS[prefix] ?? 1;

    return {
        value: formatDisplayNumber(canonicalValue / factor),
        unit: `${prefix}${normalizedUnit}`,
    };
};

const humanizeRange = (canonicalMin, canonicalMax, normalizedUnit, fallbackUnit) => {
    if (!canHumanize(normalizedUnit)) {
        return {
            min: formatDisplayNumber(canonicalMin),
            max: formatDisplayNumber(canonicalMax),
            unit: fallbackUnit || normalizedUnit,
        };
    }

    const prefix = choosePrefix(Math.max(Math.abs(canonicalMin), Math.abs(canonicalMax)));
    const factor = PREFIX_FACTORS[prefix] ?? 1;

    return {
        min: formatDisplayNumber(canonicalMin / factor),
        max: formatDisplayNumber(canonicalMax / factor),
        unit: `${prefix}${normalizedUnit}`,
    };
};

const humanizeTriple = (canonicalMin, canonicalTyp, canonicalMax, normalizedUnit, fallbackUnit) => {
    if (!canHumanize(normalizedUnit)) {
        return {
            min: formatDisplayNumber(canonicalMin),
            typ: formatDisplayNumber(canonicalTyp),
            max: formatDisplayNumber(canonicalMax),
            unit: fallbackUnit || normalizedUnit,
        };
    }

    const prefix = choosePrefix(Math.max(Math.abs(canonicalMin), Math.abs(canonicalTyp), Math.abs(canonicalMax)));
    const factor = PREFIX_FACTORS[prefix] ?? 1;

    return {
        min: formatDisplayNumber(canonicalMin / factor),
        typ: formatDisplayNumber(canonicalTyp / factor),
        max: formatDisplayNumber(canonicalMax / factor),
        unit: `${prefix}${normalizedUnit}`,
    };
};

const choosePrefix = (value) => {
    if (!value) return '';

    const abs = Math.abs(value);
    for (const prefix of HUMAN_PREFIX_ORDER) {
        const factor = PREFIX_FACTORS[prefix];
        const scaled = abs / factor;
        if (scaled >= 1 && scaled < 1000) {
            return prefix;
        }
    }

    return abs >= 1 ? '' : 'f';
};

const canHumanize = (unit) => !!unit && !['%', 'dB', '°C', '°F'].includes(unit) && /^[A-Za-zΩΩ]+$/u.test(unit);

const looksLikeRange = (value) => RANGE_SPLIT_PATTERN.test(String(value ?? ''));
const looksLikeTriple = (value) => TRIPLE_SPLIT_PATTERN.test(String(value ?? '')) && !looksLikeRange(value);

const splitRangeValue = (value) => {
    const parts = String(value ?? '').trim().split(RANGE_SPLIT_PATTERN);
    if (parts.length < 2) {
        return ['', ''];
    }

    return [parts[0]?.trim() ?? '', parts[1]?.trim() ?? ''];
};

const splitTripleValue = (value) => {
    const parts = String(value ?? '').trim().split(TRIPLE_SPLIT_PATTERN);
    if (parts.length < 3) {
        return ['', '', ''];
    }

    return [
        parts[0]?.trim() ?? '',
        parts[1]?.trim() ?? '',
        parts[2]?.trim() ?? '',
    ];
};

const extractInlineUnit = (value) => {
    const trimmed = cleanText(value);
    if (!trimmed) return { value: '', unit: '' };

    if (pregMatch(/^(.*?)([A-Za-zΩΩµμ%°/][A-Za-z0-9ΩΩµμ%°/]*)$/u, trimmed)) {
        const matches = trimmed.match(/^(.*?)([A-Za-zΩΩµμ%°/][A-Za-z0-9ΩΩµμ%°/]*)$/u);
        const candidateValue = cleanText(matches?.[1] ?? '');
        const candidateUnit = cleanText(matches?.[2] ?? '');
        if (candidateValue && candidateUnit) {
            return { value: candidateValue, unit: normalizeUnitLabel(candidateUnit) };
        }
    }

    return { value: trimmed, unit: '' };
};

const pregMatch = (pattern, value) => pattern.test(value);

const parseEngineeringNumber = (value) => {
    const normalized = cleanText(value)
        .replace(/,/g, '')
        .replace(/\s+/g, '');

    if (!normalized) return null;

    const matches = normalized.match(/^([-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?)([YZEPTGMkKmunpfµμ]?)$/u);
    if (!matches) {
        return null;
    }

    const parsed = Number(matches[1]);
    if (!Number.isFinite(parsed)) {
        return null;
    }

    const rawPrefix = matches[2] || '';
    const prefix = rawPrefix === 'µ' || rawPrefix === 'μ' ? 'u' : rawPrefix;
    if (!Object.hasOwn(ENGINEERING_VALUE_PREFIX_FACTORS, prefix)) {
        return null;
    }

    return {
        value: parsed,
        factor: ENGINEERING_VALUE_PREFIX_FACTORS[prefix],
    };
};

const normalizeUnitLabel = (value) => {
    const normalized = cleanText(value)
        .replaceAll('μ', 'u')
        .replaceAll('µ', 'u')
        .replaceAll('Ω', 'Ω')
        .replace(/\bohms?\b/iu, 'Ω')
        .replace(/\bohm\b/iu, 'Ω');

    return normalized.replace(/^K(?=[A-Za-zΩ])/u, 'k');
};

const buildRangeLabel = (min, max) => [cleanText(min), cleanText(max)].filter(Boolean).join(' 〜 ');
const buildTripleLabel = (min, typ, max) => [cleanText(min), cleanText(typ), cleanText(max)].filter(Boolean).join(' / ');

const cleanText = (value) => String(value ?? '').trim();
const hasValue = (value) => cleanText(value) !== '';

const formatDisplayNumber = (value) => {
    if (!Number.isFinite(Number(value))) {
        return '';
    }

    const normalized = Number(value);
    if (normalized === 0) return '0';

    if (Math.abs(normalized) >= 1000 || Math.abs(normalized) < 0.001) {
        return formatScientific(normalized);
    }

    return normalized.toLocaleString('en-US', {
        maximumFractionDigits: 6,
        useGrouping: false,
    }).replace(/(?:\.0+|(\.\d*?)0+)$/, '$1');
};

const formatScientific = (value) => {
    const normalized = Number(value);
    if (!Number.isFinite(normalized)) return '';
    if (normalized === 0) return '0';

    const text = normalized.toExponential(6);
    const [coefficient, exponent] = text.split('e');
    const trimmedCoefficient = coefficient.replace(/(?:\.0+|(\.\d*?)0+)$/, '$1');
    const normalizedExponent = exponent.replace(/^\+/, '').replace(/^(-?)0+(\d)/, '$1$2');

    return `${trimmedCoefficient}e${normalizedExponent}`;
};
