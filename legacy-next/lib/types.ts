export type MediaType = "anime" | "manga"

export type MediaFormat =
  | "TV"
  | "TV_SHORT"
  | "MOVIE"
  | "SPECIAL"
  | "OVA"
  | "ONA"
  | "MUSIC"
  | "MANGA"
  | "NOVEL"
  | "ONE_SHOT"

export type MediaStatus = "FINISHED" | "RELEASING" | "NOT_YET_RELEASED" | "CANCELLED" | "HIATUS"

export type MediaSeason = "WINTER" | "SPRING" | "SUMMER" | "FALL"

export interface MediaTitle {
  romaji?: string | null
  english?: string | null
  native?: string | null
}

export interface MediaRelation {
  id: number
  type: MediaType
  relationType?: string | null
  title: string
  coverImage?: string | null
  format?: string | null
}

export interface MediaCharacter {
  id: number
  name: string
  image?: string | null
  role?: string | null
  voiceActor?: string | null
}

/**
 * Normalized media item shared across AniList and Jikan sources.
 */
export interface MediaItem {
  id: number
  type: MediaType
  title: MediaTitle
  displayTitle: string
  description?: string | null
  coverImage?: string | null
  bannerImage?: string | null
  color?: string | null
  format?: MediaFormat | string | null
  status?: MediaStatus | string | null
  averageScore?: number | null // 0-100
  meanScore?: number | null
  popularity?: number | null
  favourites?: number | null
  episodes?: number | null
  chapters?: number | null
  volumes?: number | null
  duration?: number | null
  season?: MediaSeason | null
  seasonYear?: number | null
  startYear?: number | null
  genres: string[]
  studios: string[]
  authors: string[]
  isAdult?: boolean
  relations?: MediaRelation[]
  characters?: MediaCharacter[]
  recommendations?: MediaItem[]
  trailer?: { id: string; site: string } | null
}

export interface SearchResponse {
  items: MediaItem[]
  pageInfo: {
    total: number
    currentPage: number
    lastPage: number
    hasNextPage: boolean
    perPage: number
  }
}

export interface SearchParams {
  query?: string
  type: MediaType
  genre?: string
  format?: string
  season?: MediaSeason
  seasonYear?: number
  status?: string
  sort?: string
  page?: number
  perPage?: number
}

/* ---------- Personal list (localStorage) ---------- */

export type ListStatus = "watching" | "reading" | "completed" | "planning" | "paused" | "dropped"

export interface ListEntry {
  id: number
  type: MediaType
  title: string
  coverImage?: string | null
  format?: string | null
  status: ListStatus
  averageScore?: number | null
  addedAt: number
}
