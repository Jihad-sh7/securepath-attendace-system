import { useEffect, useState } from "react";
import PageHeader from "../components/common/PageHeader.jsx";
import Tabs from "../components/common/Tabs.jsx";
import FormField from "../components/forms/FormField.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { getSettings, updateSettings } from "../services/settingsService.js";

const sections = ["General", "Attendance", "Facial recognition", "Privacy", "Security", "Notifications", "Integrations"].map((label) => ({ id: label.toLowerCase().replaceAll(" ", "-"), label }));

export default function SettingsPage() {
  const [settings, setSettings] = useState(null);
  const [active, setActive] = useState("general");
  const [saving, setSaving] = useState(false);
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const canManage = hasPermission(PERMISSIONS.SETTINGS_MANAGE);

  useEffect(() => { getSettings().then(setSettings).catch((error) => showToast(error.message, "warning")); }, []);

  function update(name, value) { setSettings((current) => ({ ...current, [name]: value })); }

  async function saveSettings() {
    setSaving(true);
    try {
      setSettings(await updateSettings(settings));
      showToast("Settings saved successfully.");
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  if (!settings) return <LoadingSkeleton rows={8} />;

  return (
    <div className="page-stack">
      <PageHeader title="System Settings" description="Configure attendance, recognition, privacy, security, notifications, and integrations." actions={canManage && <button className="button button-primary" type="button" onClick={saveSettings} disabled={saving}>{saving ? "Saving..." : "Save settings"}</button>} />
      <Tabs tabs={sections} active={active} onChange={setActive} />
      <fieldset className="settings-panel settings-fieldset" disabled={!canManage}>
        {active === "general" && <div className="form-grid"><FormField label="Organisation name"><input value={settings.organizationName} onChange={(event) => update("organizationName", event.target.value)} /></FormField><FormField label="Time zone"><input value={settings.timeZone} onChange={(event) => update("timeZone", event.target.value)} /></FormField><FormField label="Date format"><select value={settings.dateFormat} onChange={(event) => update("dateFormat", event.target.value)}><option>YYYY-MM-DD</option><option>MM/DD/YYYY</option><option>DD/MM/YYYY</option></select></FormField></div>}
        {active === "attendance" && <div className="form-grid"><FormField label="Grace period"><input type="number" value={settings.gracePeriod} onChange={(event) => update("gracePeriod", Number(event.target.value))} /></FormField><FormField label="Duplicate window (seconds)"><input type="number" value={settings.duplicateRecognitionWindowSeconds} onChange={(event) => update("duplicateRecognitionWindowSeconds", Number(event.target.value))} /></FormField><FormField label="Automatic absence rules"><input value={settings.absenceRule} onChange={(event) => update("absenceRule", event.target.value)} /></FormField></div>}
        {active === "facial-recognition" && <div className="form-grid"><FormField label={`Recognition threshold (${settings.confidenceThreshold}%)`}><input type="range" min="50" max="99" value={settings.confidenceThreshold} onChange={(event) => update("confidenceThreshold", Number(event.target.value))} /></FormField><FormField label={`Manual review threshold (${settings.manualReviewThreshold}%)`}><input type="range" min="40" max="99" value={settings.manualReviewThreshold} onChange={(event) => update("manualReviewThreshold", Number(event.target.value))} /></FormField><label className="checkbox-label"><input type="checkbox" checked={settings.livenessDetection} onChange={(event) => update("livenessDetection", event.target.checked)} /> Liveness detection</label><FormField label="Unknown-face handling"><select value={settings.unknownFaceHandling} onChange={(event) => update("unknownFaceHandling", event.target.value)}><option>Require manual review</option><option>Create alert only</option><option>Ignore after hours</option></select></FormField></div>}
        {active === "privacy" && <div className="form-grid"><FormField label="Data-retention period"><input type="number" value={settings.retentionPeriod} onChange={(event) => update("retentionPeriod", Number(event.target.value))} /></FormField><p className="settings-note">No raw facial embeddings are exposed. Delete requests are audit-visible.</p></div>}
        {active === "security" && <div className="form-grid"><FormField label="Session timeout"><input type="number" value={settings.sessionTimeout} onChange={(event) => update("sessionTimeout", Number(event.target.value))} /></FormField><FormField label="Password policy"><input value={settings.passwordPolicy} onChange={(event) => update("passwordPolicy", event.target.value)} /></FormField></div>}
        {active === "notifications" && <label className="checkbox-label"><input type="checkbox" checked={settings.emailAlerts} onChange={(event) => update("emailAlerts", event.target.checked)} /> Email critical alerts</label>}
        {active === "integrations" && <div className="form-grid"><FormField label="API base URL"><input value={import.meta.env.VITE_API_BASE_URL || ""} readOnly /></FormField><FormField label="Webhook URL"><input value={settings.webhookUrl} onChange={(event) => update("webhookUrl", event.target.value)} placeholder="https://example.com/webhook" /></FormField></div>}
      </fieldset>
    </div>
  );
}
