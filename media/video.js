// Video for the AI agents: the owner's clips are uploaded here (PUT /files),
// looked at (POST /probe: length, size, sound and a few frames the agent
// sees), and cut together by the agent's plan (POST /video): trimmed,
// sped up or slowed down, cropped to the format, joined, with the text
// overlays (transparent PNGs rendered from HTML) shown at their seconds.
// ffmpeg does the work, one video at a time; the API collects the result.
//
// Files and finished videos live in WORK_DIR for KEEP_HOURS, then go.

import { spawn } from "node:child_process";
import { randomUUID } from "node:crypto";
import fs from "node:fs";
import fsp from "node:fs/promises";
import path from "node:path";

const WORK_DIR = process.env.MEDIA_WORK_DIR || "/tmp/phay-media";
const FFMPEG = process.env.FFMPEG_PATH || "ffmpeg";
const FFPROBE = process.env.FFPROBE_PATH || "ffprobe";
/** A clip as the API accepts it (App\Assistant\Attachments::MAX_VIDEO_BYTES), with room. */
export const MAX_FILE = 100 * 1024 * 1024;
const KEEP_HOURS = 3;
const MAX_CLIPS = 10;
const MAX_OUTPUT_SECONDS = 90;
const FPS = 30;
/** Frames of a clip the agent looks at. */
const FRAMES = 4;

const ID = /^[a-f0-9-]{36}$/;
const files = path.join(WORK_DIR, "files");
const jobsDir = path.join(WORK_DIR, "jobs");
fs.mkdirSync(files, { recursive: true });
fs.mkdirSync(jobsDir, { recursive: true });

/** @type {Map<string, {status: string, progress: number, error?: string, duration: number, output: string, created: number}>} */
const jobs = new Map();
const queue = [];
let busy = false;

export class HttpError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}

function run(command, args, { onStderr, timeout = 15 * 60_000 } = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { stdio: ["ignore", "pipe", "pipe"] });
    let out = "";
    let err = "";
    const timer = setTimeout(() => child.kill("SIGKILL"), timeout);
    child.stdout.on("data", (d) => (out += d));
    child.stderr.on("data", (d) => {
      err = (err + d).slice(-20_000);
      onStderr?.(String(d));
    });
    child.on("error", reject);
    child.on("close", (code) => {
      clearTimeout(timer);
      code === 0 ? resolve(out) : reject(new Error(`${path.basename(command)} exited ${code}: ${err.slice(-600)}`));
    });
  });
}

function filePath(id) {
  if (!ID.test(String(id))) throw new HttpError(422, "Unknown file.");
  const file = path.join(files, id);
  if (!fs.existsSync(file)) throw new HttpError(404, "The file is gone: upload it again.");
  return file;
}

/** A clip from the request body, kept for the next steps. */
export async function saveFile(request) {
  const id = randomUUID();
  const target = path.join(files, id);
  const out = fs.createWriteStream(target);
  let size = 0;
  await new Promise((resolve, reject) => {
    request.on("data", (chunk) => {
      size += chunk.length;
      if (size > MAX_FILE) {
        request.destroy();
        out.destroy();
        fs.rmSync(target, { force: true });
        reject(new HttpError(413, "The file is too big."));
        return;
      }
      out.write(chunk);
    });
    request.on("end", () => out.end(resolve));
    request.on("error", reject);
  });
  return { id, size };
}

/** Length, size as shown (phone rotation applied), sound, and (unless frames is false) frames to look at. */
export async function probe(id, withFrames = true) {
  const file = filePath(id);
  let info;
  try {
    info = JSON.parse(await run(FFPROBE, ["-v", "error", "-print_format", "json", "-show_format", "-show_streams", file], { timeout: 30_000 }));
  } catch {
    throw new HttpError(422, "This is not a video that can be read.");
  }
  const video = info.streams?.find((s) => s.codec_type === "video" && s.disposition?.attached_pic !== 1);
  const duration = Number(info.format?.duration || video?.duration || 0);
  if (!video || !(duration > 0)) throw new HttpError(422, "This is not a video that can be read.");

  const rotation = Math.abs(Number(video.tags?.rotate ?? video.side_data_list?.find((d) => d.rotation !== undefined)?.rotation ?? 0)) % 180;
  const [width, height] = rotation === 90 ? [video.height, video.width] : [video.width, video.height];

  const frames = [];
  for (let i = 0; withFrames && i < FRAMES; i++) {
    const t = Math.max(0, Math.min(duration - 0.05, duration * ((i + 0.5) / FRAMES)));
    const jpg = path.join(files, `${id}-frame-${i}.jpg`);
    try {
      await run(FFMPEG, ["-v", "error", "-y", "-ss", t.toFixed(2), "-i", file, "-frames:v", "1", "-vf", "scale=480:480:force_original_aspect_ratio=decrease", "-q:v", "5", jpg], { timeout: 30_000 });
      frames.push({ t: Number(t.toFixed(1)), jpg: (await fsp.readFile(jpg)).toString("base64") });
    } catch {
      // A frame that can't be read is left out.
    } finally {
      fs.rmSync(jpg, { force: true });
    }
  }

  return {
    duration: Number(duration.toFixed(2)),
    width,
    height,
    audio: info.streams.some((s) => s.codec_type === "audio"),
    frames,
  };
}

const num = (value, min, max, fallback) => {
  const n = Number(value);
  return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : fallback;
};

/** Checks the plan and queues it; the job's id comes back at once. */
export async function startVideo(spec) {
  const width = Math.round(num(spec.width, 240, 1920, 1080));
  const height = Math.round(num(spec.height, 240, 1920, 1920));
  if (width % 2 || height % 2) throw new HttpError(422, "width and height must be even.");
  if (!Array.isArray(spec.clips) || spec.clips.length === 0 || spec.clips.length > MAX_CLIPS) {
    throw new HttpError(422, `clips: 1-${MAX_CLIPS} parts.`);
  }

  const clips = [];
  let total = 0;
  for (const clip of spec.clips) {
    const file = filePath(clip.file);
    const meta = await probe(clip.file, false);
    const start = num(clip.start, 0, meta.duration, 0);
    const end = num(clip.end, start, meta.duration, meta.duration);
    const speed = num(clip.speed, 0.5, 2, 1);
    if (end - start < 0.2) throw new HttpError(422, "A part must last at least 0.2 s.");
    total += (end - start) / speed;
    clips.push({ file, start, end, speed, audio: meta.audio && !clip.muted });
  }
  if (total > MAX_OUTPUT_SECONDS) throw new HttpError(422, `The video would last ${total.toFixed(1)} s; at most ${MAX_OUTPUT_SECONDS}.`);

  const overlays = [];
  for (const overlay of spec.overlays ?? []) {
    if (typeof overlay.png !== "string") continue;
    const png = path.join(files, `${randomUUID()}.png`);
    await fsp.writeFile(png, Buffer.from(overlay.png, "base64"));
    const start = num(overlay.start, 0, total, 0);
    overlays.push({ png, start, end: num(overlay.end, start, total, total) });
  }

  const id = randomUUID();
  const job = {
    status: "queued",
    progress: 0,
    duration: Number(total.toFixed(2)),
    output: path.join(jobsDir, `${id}.mp4`),
    created: Date.now(),
    plan: { width, height, clips, overlays, fade: spec.fade !== false },
  };
  jobs.set(id, job);
  queue.push(id);
  next();

  return { id, status: job.status, duration: job.duration };
}

export function videoStatus(id) {
  const job = ID.test(String(id)) ? jobs.get(id) : null;
  if (!job) throw new HttpError(404, "No such video (the media service may have restarted).");
  return { id, status: job.status, progress: job.progress, duration: job.duration, error: job.error ?? null };
}

export function videoFile(id) {
  const job = ID.test(String(id)) ? jobs.get(id) : null;
  if (!job || job.status !== "done") throw new HttpError(404, "The video is not ready.");
  return job.output;
}

async function next() {
  if (busy || queue.length === 0) return;
  busy = true;
  const id = queue.shift();
  const job = jobs.get(id);
  try {
    job.status = "rendering";
    await cut(job);
    job.status = "done";
    job.progress = 100;
  } catch (error) {
    job.status = "failed";
    job.error = String(error.message).slice(0, 300);
    console.error("video failed:", job.error);
  } finally {
    for (const overlay of job.plan.overlays) fs.rmSync(overlay.png, { force: true });
    delete job.plan;
    busy = false;
    next();
  }
}

/** The ffmpeg command for a plan: one filter graph, H.264 + AAC, ready for Instagram. */
export function ffmpegArgs({ width, height, clips, overlays, fade }, output, total) {
  const args = ["-v", "error", "-y", "-progress", "pipe:2"];
  for (const clip of clips) {
    args.push("-ss", clip.start.toFixed(3), "-t", (clip.end - clip.start).toFixed(3), "-i", clip.file);
  }
  for (const overlay of overlays) {
    args.push("-loop", "1", "-t", total.toFixed(3), "-i", overlay.png);
  }

  const graph = [];
  const parts = [];
  clips.forEach((clip, i) => {
    const seconds = (clip.end - clip.start) / clip.speed;
    graph.push(
      `[${i}:v]scale=${width}:${height}:force_original_aspect_ratio=increase,crop=${width}:${height},setsar=1,fps=${FPS},setpts=(PTS-STARTPTS)/${clip.speed},format=yuv420p[v${i}]`,
    );
    graph.push(
      clip.audio
        ? `[${i}:a]aresample=48000,aformat=channel_layouts=stereo,asetpts=PTS-STARTPTS,atempo=${clip.speed},atrim=0:${seconds.toFixed(3)}[a${i}]`
        : `anullsrc=r=48000:cl=stereo,atrim=0:${seconds.toFixed(3)}[a${i}]`,
    );
    parts.push(`[v${i}][a${i}]`);
  });
  graph.push(`${parts.join("")}concat=n=${clips.length}:v=1:a=1[vc][ac]`);

  let video = "vc";
  overlays.forEach((overlay, k) => {
    const input = clips.length + k;
    graph.push(`[${video}][${input}:v]overlay=0:0:enable='between(t,${overlay.start.toFixed(2)},${overlay.end.toFixed(2)})'[o${k}]`);
    video = `o${k}`;
  });

  if (fade && total > 1.5) {
    const out = Math.max(0, total - 0.4).toFixed(2);
    graph.push(`[${video}]fade=t=in:st=0:d=0.25,fade=t=out:st=${out}:d=0.4[vf]`);
    graph.push(`[ac]afade=t=in:st=0:d=0.25,afade=t=out:st=${out}:d=0.4[af]`);
  } else {
    graph.push(`[${video}]null[vf]`, `[ac]anull[af]`);
  }

  args.push(
    "-filter_complex", graph.join(";"),
    "-map", "[vf]", "-map", "[af]",
    "-c:v", "libx264", "-preset", "veryfast", "-crf", "22", "-pix_fmt", "yuv420p", "-r", String(FPS),
    "-c:a", "aac", "-b:a", "128k",
    "-movflags", "+faststart",
    "-t", total.toFixed(3),
    output,
  );
  return args;
}

async function cut(job) {
  const args = ffmpegArgs(job.plan, job.output, job.duration);
  await run(FFMPEG, args, {
    onStderr: (chunk) => {
      const match = /out_time_us=(\d+)/.exec(chunk);
      if (match) job.progress = Math.min(99, Math.round((Number(match[1]) / 1e6 / job.duration) * 100));
    },
  });
}

/** Old uploads and videos go. */
async function sweep() {
  const before = Date.now() - KEEP_HOURS * 3600_000;
  for (const dir of [files, jobsDir]) {
    for (const name of await fsp.readdir(dir).catch(() => [])) {
      const file = path.join(dir, name);
      const stat = await fsp.stat(file).catch(() => null);
      if (stat && stat.mtimeMs < before) await fsp.rm(file, { force: true });
    }
  }
  for (const [id, job] of jobs) {
    if (job.created < before) jobs.delete(id);
  }
}
setInterval(sweep, 10 * 60_000).unref();
