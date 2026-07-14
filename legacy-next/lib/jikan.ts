import type { MediaItem, MediaType, SearchResponse } from "./types"

const JIKAN_URL = "https://api.jikan.moe/v4"

function mapJikan(m: any, type: MediaType): MediaItem {
  const title = {
    romaji: m.title ?? null,
    english: m.title_english ?? null,
    native: m.title_japanese ?? null,
  }
  const displayTitle = title.english || title.romaji || title.native || "Untitled"

  return {
    id: m.mal_id,
    type,
    title,
    displayTitle,
    description: m.synopsis ?? null,
    coverImage: m.images?.webp?.large_image_url || m.images?.jpg?.large_image_url || null,
    bannerImage: null,
    color: null,
    format: m.type ?? null,
    status: m.status ?? null,
    averageScore: m.score ? Math.round(m.score * 10) : null,
    meanScore: m.score ? Math.round(m.score * 10) : null,
    popularity: m.members ?? null,
    favourites: m.favorites ?? null,
    episodes: m.episodes ?? null,
    chapters: m.chapters ?? null,
    volumes: m.volumes ?? null,
    duration: null,
    season: m.season ? (m.season.toUpperCase() as MediaItem["season"]) : null,
    seasonYear: m.year ?? null,
    startYear: m.year ?? m.published?.prop?.from?.year ?? null,
    genres: (m.genres ?? []).map((g: any) => g.name).filter(Boolean),
    studios: (m.studios ?? []).map((s: any) => s.name).filter(Boolean),
    authors: (m.authors ?? []).map((a: any) => a.name).filter(Boolean),
    isAdult: false,
  }
}

async function jikanFetch<T>(path: string, revalidate = 3600): Promise<T> {
  const res = await fetch(`${JIKAN_URL}${path}`, { next: { revalidate } })
  if (!res.ok) throw new Error(`Jikan request failed (${res.status})`)
  const json = await res.json()
  return json.data as T
}

/** Fallback search when AniList is unavailable. */
export async function jikanSearch(query: string, type: MediaType, page = 1): Promise<SearchResponse> {
  const endpoint = type === "manga" ? "manga" : "anime"
  const q = query ? `q=${encodeURIComponent(query)}&` : ""
  const data = await jikanFetch<any>(`/${endpoint}?${q}page=${page}&limit=24&sfw=true`, 300)
  const raw = Array.isArray(data) ? data : []
  return {
    items: raw.map((m: any) => mapJikan(m, type)),
    pageInfo: {
      total: raw.length,
      currentPage: page,
      lastPage: page + (raw.length >= 24 ? 1 : 0),
      hasNextPage: raw.length >= 24,
      perPage: 24,
    },
  }
}

/** Fallback details lookup by MAL id. */
export async function jikanDetails(id: number, type: MediaType): Promise<MediaItem> {
  const endpoint = type === "manga" ? "manga" : "anime"
  const data = await jikanFetch<any>(`/${endpoint}/${id}/full`)
  return mapJikan(data, type)
}
