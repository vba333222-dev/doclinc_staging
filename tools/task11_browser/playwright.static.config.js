const base = require('./playwright.config');

// Static visual/asset specs do not allocate fixtures or touch the CI3 runtime.
// Keep them on a separate config so the normal runtime config remains fail-closed
// and always performs its disposable-run cleanup.
module.exports = {
  ...base,
  globalTeardown: undefined,
  testMatch: [
    '**/accessibility_visual_refresh.spec.js',
    '**/assets.spec.js',
    '**/icon_registry.spec.js',
    '**/icon_usage.spec.js',
    '**/livekit_visual_refresh.spec.js',
    '**/livekit.spec.js',
    '**/livekit_control_state.spec.js',
    '**/livekit_final_control.spec.js',
    '**/livekit_control_capture.spec.js',
    '**/modern_controls.spec.js',
    '**/chat_composer_capture.spec.js',
    '**/active_communication_reference.spec.js',
    '**/active_communication_capture.spec.js',
    '**/strict_reference_reproduction.spec.js',
    '**/strict_harness_validation.spec.js',
    '**/reference_specs.spec.js',
    '**/workflow_ui.spec.js',
    '**/clinical_workflow_ui.spec.js',
    '**/warga_incoming_call_ui.spec.js',
    '**/chat_incoming_authoritative.spec.js',
  ],
};
