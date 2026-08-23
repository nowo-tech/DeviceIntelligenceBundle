/**
 * Bundle logger for Device Intelligence (same API as other Nowo bundles).
 *
 * `scriptLoaded()` always logs. `debug` / `info` / `warn` / `error` require
 * `setDebug(true)` unless `alwaysLog` is set.
 */

export type BundleLoggerOptions = {
  /** If set, scriptLoaded() includes this (Vite injects compile time). */
  buildTime?: string;
  /** When true, debug/info/warn/error always output. */
  alwaysLog?: boolean;
};

export type BundleLogger = {
  /** Call once at startup. Logs "script loaded" and optional build time. */
  scriptLoaded: () => void;
  setDebug: (enabled: boolean) => void;
  debug: (...args: unknown[]) => void;
  info: (...args: unknown[]) => void;
  warn: (...args: unknown[]) => void;
  error: (...args: unknown[]) => void;
};

const STYLES = {
  script: 'color:#0ea5e9;font-weight:bold',
  debug: 'color:#6b7280',
  info: 'color:#2563eb',
  warn: 'color:#d97706',
  error: 'color:#dc2626;font-weight:bold',
} as const;

const EMOJI = {
  script: '📦',
  debug: '🔍',
  info: 'ℹ️',
  warn: '⚠️',
  error: '❌',
} as const;

function formatArgs(args: unknown[]): unknown[] {
  return args.map((a) =>
    typeof a === 'object' && a !== null && !(a instanceof Error) ? JSON.stringify(a) : a,
  );
}

type ConsoleLevel = 'debug' | 'info' | 'warn' | 'error';

function logScriptLoaded(prefix: string, buildTime: string | undefined): void {
  if (buildTime !== undefined && buildTime !== '') {
    console.log(
      `%c${EMOJI.script} ${prefix} script loaded, build time: %c${buildTime}`,
      STYLES.script,
      'color:#059669',
    );
    return;
  }
  console.log(`%c${EMOJI.script} ${prefix} script loaded`, STYLES.script);
}

function emitLevelLog(level: ConsoleLevel, prefix: string, args: unknown[]): void {
  const emoji = EMOJI[level];
  const style = STYLES[level];
  const label = `%c${emoji} ${prefix}`;
  const logFn = console[level] as (...fnArgs: unknown[]) => void;
  if (args.length > 0) {
    logFn(label, style, ...formatArgs(args));
    return;
  }
  logFn(label, style);
}

function makeLevelMethod(
  isEnabled: () => boolean,
  prefix: string,
  level: ConsoleLevel,
): (...args: unknown[]) => void {
  return (...args: unknown[]): void => {
    if (!isEnabled()) {
      return;
    }
    emitLevelLog(level, prefix, args);
  };
}

function noop(): void {}

let instance: BundleLogger | null = null;

/**
 * Registers the bundle logger. Call once from the entry after createBundleLogger.
 *
 * @param log - Logger instance to register
 */
export function setBundleLogger(log: BundleLogger): void {
  instance = log;
}

/**
 * Clears the registered logger (tests only).
 */
export function clearBundleLoggerForTest(): void {
  instance = null;
}

/**
 * Returns the bundle logger, or a no-op logger when none is registered.
 *
 * @returns The registered logger or a silent fallback
 */
export function getLogger(): BundleLogger {
  if (instance !== null) {
    return instance;
  }
  return {
    scriptLoaded: noop,
    setDebug: noop,
    debug: noop,
    info: noop,
    warn: noop,
    error: noop,
  };
}

/**
 * Creates a Device Intelligence console logger.
 *
 * @param name - Short name for the log prefix (e.g. `device-intelligence`)
 * @param options - Optional buildTime and alwaysLog
 * @returns A BundleLogger
 */
export function createBundleLogger(name: string, options: BundleLoggerOptions = {}): BundleLogger {
  const prefix = `[${name}]`;
  const { buildTime, alwaysLog = false } = options;
  const logAlways = alwaysLog === true;
  let debugEnabled = logAlways;

  return {
    scriptLoaded(): void {
      logScriptLoaded(prefix, buildTime);
    },

    setDebug(enabled: boolean): void {
      debugEnabled = logAlways ? true : enabled;
    },

    debug: makeLevelMethod(() => debugEnabled, prefix, 'debug'),
    info: makeLevelMethod(() => debugEnabled, prefix, 'info'),
    warn: makeLevelMethod(() => debugEnabled, prefix, 'warn'),
    error: makeLevelMethod(() => debugEnabled, prefix, 'error'),
  };
}
