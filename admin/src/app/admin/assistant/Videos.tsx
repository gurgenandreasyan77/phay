"use client";

import { useEffect, useState } from "react";
import { AlertCircle, Download, Film } from "lucide-react";
import type { AssistantVideoDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { attachmentUrl, formatDuration } from "./Attachments";

/** How often a rendering video is asked about. */
const POLL_MS = 3000;

/** reel 9:16, feed 4:5, square 1:1, landscape 16:9 (App\Media\VideoFormat). */
const RATIO: Record<string, string> = { reel: "9 / 16", feed: "4 / 5", square: "1 / 1", landscape: "16 / 9" };

/** The videos an agent cut in its answer: each renders for a minute or two, then plays here. */
export function VideoList({ videos }: { videos: AssistantVideoDto[] }) {
  if (videos.length === 0) return null;
  return (
    <div className={cn("mt-3 grid gap-3", videos.length > 1 ? "sm:grid-cols-2" : "max-w-xs")}>
      {videos.map((video) => (
        <VideoCard key={video.id} initial={video} />
      ))}
    </div>
  );
}

function VideoCard({ initial }: { initial: AssistantVideoDto }) {
  const [video, setVideo] = useState(initial);

  // Asks until it is done or failed; asking also collects it in the API.
  useEffect(() => {
    if (video.status !== "rendering") return;
    let stopped = false;
    const timer = window.setTimeout(async () => {
      const response = await fetch(`/api/admin/assistant/videos/${video.id}`, { cache: "no-store" }).catch(() => null);
      const json = response?.ok ? await response.json().catch(() => null) : null;
      if (stopped) return;
      // A failed ask keeps the card as it is; the timer runs again on the next state.
      setVideo((current) => (json?.data ? { ...current, ...json.data } : { ...current }));
    }, POLL_MS);
    return () => {
      stopped = true;
      window.clearTimeout(timer);
    };
  }, [video]);

  const ratio = RATIO[video.format] ?? "9 / 16";
  const file = video.status === "done" ? video.attachment : null;

  return (
    <figure className="overflow-hidden rounded-md border border-[var(--tint-soft)] bg-surface">
      {file ? (
        <video
          src={`${attachmentUrl(file.id)}#t=0.4`}
          controls
          playsInline
          preload="metadata"
          className="block max-h-[480px] w-full bg-black"
          style={{ aspectRatio: ratio }}
          aria-label={video.title}
        />
      ) : (
        <div
          className="relative flex max-h-[480px] w-full flex-col items-center justify-center gap-3 overflow-hidden bg-ink px-6 text-center text-white"
          style={{ aspectRatio: ratio }}
          role="status"
        >
          {video.status === "failed" ? (
            <>
              <AlertCircle className="h-8 w-8 text-white/80" aria-hidden="true" />
              <p className="text-sm font-medium">Տեսանյութը չհաջողվեց պատրաստել</p>
              {video.error ? <p className="text-xs text-white/70">{video.error}</p> : null}
              <p className="text-xs text-white/70">Խնդրեք նորից մոնտաժել։</p>
            </>
          ) : (
            <>
              <span className="absolute inset-0 animate-pulse bg-[radial-gradient(circle_at_50%_40%,var(--tint)_0%,transparent_65%)] opacity-40 motion-reduce:animate-none" />
              <Film className="relative h-8 w-8" aria-hidden="true" />
              <p className="relative text-sm font-medium">Մոնտաժվում է…</p>
              <div className="relative h-1.5 w-full max-w-40 overflow-hidden rounded-full bg-white/20">
                <div
                  className="h-full rounded-full bg-white transition-[width] duration-700"
                  style={{ width: `${Math.max(6, video.progress ?? 0)}%` }}
                />
              </div>
              <p className="relative text-xs text-white/70">Սովորաբար 1-2 րոպե։ Կարող եք շարունակել զրույցը։</p>
            </>
          )}
        </div>
      )}
      <figcaption className="flex items-center gap-2 p-2">
        <div className="min-w-0 flex-1">
          <p className="truncate text-xs font-medium text-ink">{video.title}</p>
          <p className="text-[11px] text-ink-muted">{formatDuration(video.duration)}</p>
        </div>
        {file ? (
          <a
            href={`${attachmentUrl(file.id)}?download=1`}
            download={file.name}
            className="flex min-h-10 min-w-10 items-center justify-center rounded-md border border-border text-ink-muted hover:bg-surface-secondary hover:text-ink sm:min-h-8 sm:min-w-8"
            aria-label={`Ներբեռնել ${video.title}`}
            title="Ներբեռնել MP4"
          >
            <Download className="h-4 w-4" aria-hidden="true" />
          </a>
        ) : null}
      </figcaption>
    </figure>
  );
}
