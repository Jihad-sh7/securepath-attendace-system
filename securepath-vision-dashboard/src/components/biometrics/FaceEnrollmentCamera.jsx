import { Camera, Check, CheckCircle2, RefreshCw, ShieldCheck, VideoOff } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import Modal from "../common/Modal.jsx";
import StatusBadge from "../common/StatusBadge.jsx";
import { enrollBiometric, reenrollBiometric } from "../../services/peopleService.js";

const poses = [
  { id: "front", label: "Front", title: "Front face", instruction: "Look directly at the camera." },
  { id: "left", label: "Left", title: "Slight left", instruction: "Turn your face slightly to your left." },
  { id: "right", label: "Right", title: "Slight right", instruction: "Turn your face slightly to your right." }
];

function cameraMessage(error) {
  if (!navigator.mediaDevices?.getUserMedia) return "This browser does not support camera access.";
  if (error?.name === "NotAllowedError" || error?.name === "SecurityError") return "Camera permission was denied. Allow camera access in the browser, then try again.";
  if (error?.name === "NotFoundError" || error?.name === "DevicesNotFoundError") return "No camera was found on this device.";
  if (error?.name === "NotReadableError" || error?.name === "TrackStartError") return "The camera is unavailable or already being used by another application.";
  return "The camera stream was interrupted. Check the camera and try again.";
}

export default function FaceEnrollmentCamera({ open, person, mode = "enroll", onClose, onSuccess }) {
  const videoRef = useRef(null);
  const canvasRef = useRef(null);
  const streamRef = useRef(null);
  const previewsRef = useRef({});
  const [cameraState, setCameraState] = useState("starting");
  const [cameraError, setCameraError] = useState("");
  const [images, setImages] = useState({});
  const [previews, setPreviews] = useState({});
  const [poseIndex, setPoseIndex] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [consentAccepted, setConsentAccepted] = useState(person?.consent === "Granted");

  const stopCamera = useCallback(() => {
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
    if (videoRef.current) videoRef.current.srcObject = null;
  }, []);

  const startCamera = useCallback(async () => {
    stopCamera();
    setCameraState("starting");
    setCameraError("");
    try {
      if (!navigator.mediaDevices?.getUserMedia) throw new DOMException("Camera unsupported", "NotSupportedError");
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
      streamRef.current = stream;
      stream.getVideoTracks()[0]?.addEventListener("ended", () => {
        setCameraState("error");
        setCameraError("The camera stream stopped. Reconnect the camera and try again.");
      }, { once: true });
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }
      setCameraState("ready");
    } catch (error) {
      stopCamera();
      setCameraState("error");
      setCameraError(cameraMessage(error));
    }
  }, [stopCamera]);

  useEffect(() => {
    if (!open) return undefined;
    Object.values(previewsRef.current).forEach((url) => URL.revokeObjectURL(url));
    previewsRef.current = {};
    setImages({});
    setPreviews({});
    setPoseIndex(0);
    setSubmitError("");
    setSubmitting(false);
    setConsentAccepted(person?.consent === "Granted");
    startCamera();
    return stopCamera;
  }, [open, person?.id, person?.consent, startCamera, stopCamera]);

  useEffect(() => { previewsRef.current = previews; }, [previews]);
  useEffect(() => () => Object.values(previewsRef.current).forEach((url) => URL.revokeObjectURL(url)), []);

  function capture() {
    const video = videoRef.current;
    const canvas = canvasRef.current;
    if (!video?.videoWidth || !video?.videoHeight || !canvas) {
      setCameraError("The camera image is not ready yet. Wait a moment and try again.");
      return;
    }
    const width = Math.min(video.videoWidth, 1280);
    const height = Math.round((video.videoHeight / video.videoWidth) * width);
    if (width < 320 || height < 240) {
      setCameraError("Camera resolution is too low. Use a camera that provides at least 320 x 240 pixels.");
      return;
    }
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext("2d");
    context.translate(width, 0);
    context.scale(-1, 1);
    context.drawImage(video, 0, 0, width, height);
    canvas.toBlob((blob) => {
      if (!blob || blob.type !== "image/jpeg" || blob.size > 5 * 1024 * 1024) {
        setCameraError("The captured image could not be prepared. Try again in better lighting.");
        return;
      }
      const pose = poses[poseIndex].id;
      setImages((current) => ({ ...current, [pose]: blob }));
      setPreviews((current) => {
        if (current[pose]) URL.revokeObjectURL(current[pose]);
        return { ...current, [pose]: URL.createObjectURL(blob) };
      });
      setCameraError("");
      if (poseIndex < poses.length - 1) setPoseIndex(poseIndex + 1);
    }, "image/jpeg", 0.86);
  }

  function retake(index) {
    const pose = poses[index].id;
    setPoseIndex(index);
    setImages((current) => { const next = { ...current }; delete next[pose]; return next; });
    setPreviews((current) => {
      const next = { ...current };
      if (next[pose]) URL.revokeObjectURL(next[pose]);
      delete next[pose];
      return next;
    });
  }

  async function submit() {
    if (!poses.every(({ id }) => images[id])) {
      setSubmitError("Capture all three required photos before enrollment.");
      return;
    }
    if (!consentAccepted) {
      setSubmitError("Biometric consent must be confirmed before enrollment.");
      return;
    }
    setSubmitting(true);
    setSubmitError("");
    try {
      const result = mode === "reenroll"
        ? await reenrollBiometric(person.id, images, consentAccepted)
        : await enrollBiometric(person.id, images, consentAccepted);
      stopCamera();
      await onSuccess?.(result);
    } catch (error) {
      setSubmitError(error.message);
    } finally {
      setSubmitting(false);
    }
  }

  function close() {
    if (submitting) return;
    stopCamera();
    onClose?.();
  }

  const current = poses[poseIndex];
  const complete = poses.every(({ id }) => images[id]);

  return (
    <Modal open={open} title={mode === "reenroll" ? "Re-Enroll Face" : "Face Enrollment"} size="lg" onClose={close} footer={<>
      <button className="button button-ghost" type="button" onClick={close} disabled={submitting}>Cancel</button>
      <button className="button button-primary" type="button" onClick={submit} disabled={!complete || !consentAccepted || submitting}>
        <ShieldCheck size={17} /> {submitting ? "Uploading and processing..." : "Submit enrollment"}
      </button>
    </>}>
      <div className="face-enrollment">
        <header className="enrollment-person">
          <div><strong>{person?.name}</strong><span>{person?.id} · {person?.department} · {person?.role}</span></div>
          <StatusBadge status={person?.faceStatus || "Not Enrolled"} />
        </header>

        <div className="enrollment-layout">
          <section className="camera-stage">
            {cameraState === "error" ? <div className="camera-empty"><VideoOff size={34} /><strong>Camera unavailable</strong><p>{cameraError}</p><button className="button button-secondary" type="button" onClick={startCamera}><RefreshCw size={17} /> Try again</button></div> : <>
              <video ref={videoRef} muted playsInline aria-label="Face enrollment camera preview" />
              <div className="face-guide" aria-hidden="true" />
              {cameraState === "starting" && <div className="camera-loading">Starting camera...</div>}
            </>}
            <canvas ref={canvasRef} hidden />
          </section>

          <aside className="capture-panel">
            <span className="capture-step">Step {poseIndex + 1} of 3</span>
            <h3>{complete ? "Review enrollment" : current.title}</h3>
            <p>{complete ? "Review all three photos. Retake any image that is unclear." : current.instruction}</p>
            {!complete && <button className="button button-primary" type="button" onClick={capture} disabled={cameraState !== "ready"}><Camera size={17} /> Capture {current.label}</button>}
          </aside>
        </div>

        <div className="enrollment-samples">
          {poses.map((pose, index) => <article key={pose.id} className={images[pose.id] ? "captured" : ""}>
            <div className="sample-preview">{previews[pose.id] ? <img src={previews[pose.id]} alt={`${pose.label} enrollment preview`} /> : <Camera size={24} />}</div>
            <div><strong>{pose.label}</strong><span>{images[pose.id] ? <><Check size={14} /> Captured</> : "Required"}</span></div>
            {images[pose.id] && <button className="icon-button" type="button" title={`Retake ${pose.label}`} aria-label={`Retake ${pose.label}`} onClick={() => retake(index)}><RefreshCw size={16} /></button>}
          </article>)}
        </div>

        <label className="checkbox-label enrollment-consent"><input type="checkbox" checked={consentAccepted} onChange={(event) => setConsentAccepted(event.target.checked)} /><span>I confirm the employee has consented to supervised biometric enrollment.</span></label>
        {cameraState !== "error" && cameraError && <p className="form-error">{cameraError}</p>}
        {complete && !submitError && <p className="enrollment-ready"><CheckCircle2 size={17} /> Three photos are ready for secure upload.</p>}
        {submitError && <p className="form-error">{submitError}</p>}
      </div>
    </Modal>
  );
}
