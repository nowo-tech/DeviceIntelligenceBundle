# Web Profiler

Panel id: `nowo_device_intelligence`. Twig namespace: `NowoDeviceIntelligenceBundle`. Translation domain: **`NowoDeviceIntelligenceBundle`** (locales `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`).

Shows device id, new/existing, confidence, similarity, stability, risk score/level, truncated signal summaries, risk reasons, and phase timings.

The panel is filled for `POST /_device/collect` and for later HTML requests that send the observation cookie (`di_obs`). The browser client sends `X-Previous-Debug-Token` when the Web Debug Toolbar is on the page; the bundle then copies that collect analysis onto the HTML request’s stored profile so Device Intelligence on `GET /es` shows the observation (marked as Ajax). Enable `observe_on_every_request` (the Symfony 8 demo does) so a reload after `collect()` also turns the page toolbar green from the cookie.

The toolbar uses a fingerprint icon (`@NowoDeviceIntelligenceBundle/Icon/device-intelligence.svg`). Yellow means this HTML request had no `_device` yet when the page was rendered; green means an observation is attached.

Raw high-entropy values and observation token secrets are not dumped. Risk reason ids and signal names stay as technical identifiers.
