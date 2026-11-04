import { apiData } from "./apiClient.js";

export function getSettings() {
  return apiData("/settings").then((settings) => ({
    ...settings,
    confidenceThreshold: Math.round(settings.confidenceThreshold * 100),
    manualReviewThreshold: Math.round(settings.manualReviewThreshold * 100)
  }));
}

export function updateSettings(settings) {
  return apiData("/settings", {
    method: "PUT",
    body: {
      settings: {
        ...settings,
        confidenceThreshold: settings.confidenceThreshold / 100,
        manualReviewThreshold: settings.manualReviewThreshold / 100
      }
    }
  }).then((updated) => ({
    ...updated,
    confidenceThreshold: Math.round(updated.confidenceThreshold * 100),
    manualReviewThreshold: Math.round(updated.manualReviewThreshold * 100)
  }));
}
