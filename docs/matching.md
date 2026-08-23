# Matching

Identity is **not** `sha256(all_signals)`.

1. Select at most 64 candidates using `os_family`, `browser_family`, `last_seen_at`, then GPU / timezone / a 16-bit blocking key.
2. Weighted similarity (defaults sum to 1.0): webgl 0.20, canvas 0.18, audio 0.12, platform 0.10, capabilities 0.10, client_hints 0.10, screen 0.08, hardware 0.07, timezone 0.05.
3. Confidence combines similarity with coverage, quality, historical stability, contradictions, and a close second candidate.

A minor browser update (Chrome 143 → 144) must not create a new Device. An OS family swap must not attach.

Replace the algorithm by decorating `DeviceMatcherInterface`. Do not vote inside match events.
