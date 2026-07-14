import { FORMAT_LABELS, STATUS_LABELS } from "./constants"
import type { MediaItem } from "./types"

export function formatScore(score?: number | null): string | null {
  if (!score) return null
  return (score / 10).toFixed(1)
}

export function formatLabel(format?: string | null): string {
  if (!format) return "—"
  return FORMAT_LABELS[format] ?? format
}

export function statusLabel(status?: string | null): string {
  if (!status) return "—"
  return STATUS_LABELS[status] ?? status
}

export function stripHtml(text?: string | null): string {
  if (!text) return ""
  return text
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<[^>]+>/g, "")
    .replace(/\n{3,}/g, "\n\n")
    .trim()
}

export function metaLine(item: MediaItem): string {
  const parts: string[] = []
  if (item.format) parts.push(formatLabel(item.format))
  if (item.type === "anime" && item.episodes) parts.push(`${item.episodes} bölüm`)
  if (item.type === "manga" && item.chapters) parts.push(`${item.chapters} bölüm`)
  const year = item.seasonYear ?? item.startYear
  if (year) parts.push(String(year))
  return parts.join(" • ")
}

export function formatNumber(n?: number | null): string {
  if (!n) return "0"
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`
  return String(n)
}
