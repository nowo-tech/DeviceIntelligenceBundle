# Web Profiler

Panel id: `nowo_device_intelligence`. Twig namespace: `NowoDeviceIntelligenceBundle`. Translation domain: **`NowoDeviceIntelligenceBundle`** (locales `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`).

Shows device id, new/existing, confidence, similarity, stability, risk score/level, truncated signal summaries, risk reasons, and phase timings.

The panel is filled for `POST /_device/collect` and for later HTML requests that send the observation cookie (`di_obs`). Enable `observe_on_every_request` (the Symfony 8 demo does) so a reload after `collect()` shows data in the page toolbar. The first HTML request still has no cookie: use the Ajax tab for the collect POST, then reload.

Raw high-entropy values and observation token secrets are not dumped. Risk reason ids and signal names stay as technical identifiers.
