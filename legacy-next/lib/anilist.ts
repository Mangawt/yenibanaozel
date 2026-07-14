import type { MediaItem, MediaType, SearchParams, SearchResponse } from "./types"

const ANILIST_URL = "https://graphql.anilist.co"

const MEDIA_FIELDS = `
  id
  type
  title { romaji english native }
  description(asHtml: false)
  coverImage { extraLarge large color }
  bannerImage
  format
  status
  averageScore
  meanScore
  popularity
  favourites
  episodes
  chapters
  volumes
  duration
  season
  seasonYear
  startDate { year }
  genres
  isAdult
  studios(isMain: true) { nodes { name } }
  staff(perPage: 3) { edges { role node { name { full } } } }
`

function mapMedia(m: any): MediaItem {
  const type: MediaType = m.type === "MANGA" ? "manga" : "anime"
  const title = {
    romaji: m.title?.romaji ?? null,
    english: m.title?.english ?? null,
    native: m.title?.native ?? null,
  }
  const displayTitle = title.english || title.romaji || title.native || "Untitled"

  const authors: string[] =
    m.staff?.edges
      ?.filter((e: any) => /story|art|original/i.test(e.role ?? ""))
      .map((e: any) => e.node?.name?.full)
      .filter(Boolean) ?? []

  return {
    id: m.id,
    type,
    title,
    displayTitle,
    description: m.description ?? null,
    coverImage: m.coverImage?.extraLarge || m.coverImage?.large || null,
    bannerImage: m.bannerImage ?? null,
    color: m.coverImage?.color ?? null,
    format: m.format ?? null,
    status: m.status ?? null,
    averageScore: m.averageScore ?? null,
    meanScore: m.meanScore ?? null,
    popularity: m.popularity ?? null,
    favourites: m.favourites ?? null,
    episodes: m.episodes ?? null,
    chapters: m.chapters ?? null,
    volumes: m.volumes ?? null,
    duration: m.duration ?? null,
    season: m.season ?? null,
    seasonYear: m.seasonYear ?? null,
    startYear: m.startDate?.year ?? null,
    genres: m.genres ?? [],
    studios: m.studios?.nodes?.map((n: any) => n.name).filter(Boolean) ?? [],
    authors,
    isAdult: m.isAdult ?? false,
  }
}

async function gql<T>(query: string, variables: Record<string, unknown>, revalidate = 3600): Promise<T> {
  const res = await fetch(ANILIST_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ query, variables }),
    next: { revalidate },
  })

  if (!res.ok) {
    const text = await res.text().catch(() => "")
    throw new Error(`AniList request failed (${res.status}): ${text.slice(0, 200)}`)
  }

  const json = await res.json()
  if (json.errors?.length) {
    throw new Error(`AniList error: ${json.errors[0]?.message ?? "unknown"}`)
  }
  return json.data as T
}

export async function searchMedia(params: SearchParams): Promise<SearchResponse> {
  const {
    query,
    type,
    genre,
    format,
    season,
    seasonYear,
    status,
    sort = "POPULARITY_DESC",
    page = 1,
    perPage = 24,
  } = params

  const gqlQuery = `
    query (
      $page: Int, $perPage: Int, $type: MediaType, $search: String,
      $genre: String, $format: MediaFormat, $season: MediaSeason,
      $seasonYear: Int, $status: MediaStatus, $sort: [MediaSort]
    ) {
      Page(page: $page, perPage: $perPage) {
        pageInfo { total currentPage lastPage hasNextPage perPage }
        media(
          type: $type, search: $search, genre: $genre, format: $format,
          season: $season, seasonYear: $seasonYear, status: $status,
          sort: $sort, isAdult: false
        ) {
          ${MEDIA_FIELDS}
        }
      }
    }
  `

  const variables: Record<string, unknown> = {
    page,
    perPage,
    type: type === "manga" ? "MANGA" : "ANIME",
    search: query || undefined,
    genre: genre || undefined,
    format: format || undefined,
    season: season || undefined,
    seasonYear: seasonYear || undefined,
    status: status || undefined,
    sort: query ? ["SEARCH_MATCH", sort] : [sort],
  }

  const data = await gql<{ Page: { pageInfo: any; media: any[] } }>(gqlQuery, variables, query ? 300 : 3600)

  return {
    items: data.Page.media.map(mapMedia),
    pageInfo: data.Page.pageInfo,
  }
}

export async function getMediaDetails(id: number, type: MediaType): Promise<MediaItem> {
  const gqlQuery = `
    query ($id: Int, $type: MediaType) {
      Media(id: $id, type: $type) {
        ${MEDIA_FIELDS}
        trailer { id site }
        relations {
          edges {
            relationType
            node {
              id type format
              title { romaji english native }
              coverImage { large color }
            }
          }
        }
        characters(perPage: 12, sort: ROLE) {
          edges {
            role
            node { id name { full } image { large } }
            voiceActors(language: JAPANESE, sort: RELEVANCE) { name { full } }
          }
        }
        recommendations(perPage: 12, sort: RATING_DESC) {
          nodes {
            mediaRecommendation {
              ${MEDIA_FIELDS}
            }
          }
        }
      }
    }
  `

  const data = await gql<{ Media: any }>(gqlQuery, {
    id,
    type: type === "manga" ? "MANGA" : "ANIME",
  })

  const m = data.Media
  const item = mapMedia(m)

  item.trailer = m.trailer?.id ? { id: m.trailer.id, site: m.trailer.site } : null

  item.relations =
    m.relations?.edges?.map((e: any) => ({
      id: e.node.id,
      type: e.node.type === "MANGA" ? "manga" : "anime",
      relationType: e.relationType,
      title: e.node.title?.english || e.node.title?.romaji || e.node.title?.native || "Untitled",
      coverImage: e.node.coverImage?.large ?? null,
      format: e.node.format ?? null,
    })) ?? []

  item.characters =
    m.characters?.edges?.map((e: any) => ({
      id: e.node.id,
      name: e.node.name?.full ?? "Unknown",
      image: e.node.image?.large ?? null,
      role: e.role ?? null,
      voiceActor: e.voiceActors?.[0]?.name?.full ?? null,
    })) ?? []

  item.recommendations =
    m.recommendations?.nodes
      ?.map((n: any) => (n.mediaRecommendation ? mapMedia(n.mediaRecommendation) : null))
      .filter(Boolean) ?? []

  return item
}

/* ---------- Discover (homepage) lists ---------- */

const DISCOVER_QUERY = `
  query ($type: MediaType, $season: MediaSeason, $seasonYear: Int) {
    trending: Page(page: 1, perPage: 20) {
      media(type: $type, sort: TRENDING_DESC, isAdult: false) { ${MEDIA_FIELDS} }
    }
    popular: Page(page: 1, perPage: 20) {
      media(type: $type, sort: POPULARITY_DESC, isAdult: false) { ${MEDIA_FIELDS} }
    }
    top: Page(page: 1, perPage: 20) {
      media(type: $type, sort: SCORE_DESC, isAdult: false) { ${MEDIA_FIELDS} }
    }
    seasonal: Page(page: 1, perPage: 20) {
      media(type: $type, season: $season, seasonYear: $seasonYear, sort: POPULARITY_DESC, isAdult: false) { ${MEDIA_FIELDS} }
    }
  }
`

export interface DiscoverData {
  trending: MediaItem[]
  popular: MediaItem[]
  top: MediaItem[]
  seasonal: MediaItem[]
}

function currentSeason(): { season: string; year: number } {
  const now = new Date()
  const month = now.getMonth()
  const year = now.getFullYear()
  if (month <= 1) return { season: "WINTER", year }
  if (month <= 4) return { season: "SPRING", year }
  if (month <= 7) return { season: "SUMMER", year }
  if (month <= 10) return { season: "FALL", year }
  return { season: "WINTER", year: year + 1 }
}

export async function getDiscover(type: MediaType): Promise<DiscoverData> {
  const { season, year } = currentSeason()
  const data = await gql<{
    trending: { media: any[] }
    popular: { media: any[] }
    top: { media: any[] }
    seasonal: { media: any[] }
  }>(
    DISCOVER_QUERY,
    {
      type: type === "manga" ? "MANGA" : "ANIME",
      season: type === "manga" ? undefined : season,
      seasonYear: type === "manga" ? undefined : year,
    },
    3600,
  )

  return {
    trending: data.trending.media.map(mapMedia),
    popular: data.popular.media.map(mapMedia),
    top: data.top.media.map(mapMedia),
    seasonal: type === "manga" ? [] : data.seasonal.media.map(mapMedia),
  }
}
