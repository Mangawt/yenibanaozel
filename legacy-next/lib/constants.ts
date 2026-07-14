import type { ListStatus, MediaType } from "./types"

export const GENRES = [
  "Action",
  "Adventure",
  "Comedy",
  "Drama",
  "Ecchi",
  "Fantasy",
  "Horror",
  "Mahou Shoujo",
  "Mecha",
  "Music",
  "Mystery",
  "Psychological",
  "Romance",
  "Sci-Fi",
  "Slice of Life",
  "Sports",
  "Supernatural",
  "Thriller",
] as const

export const ANIME_FORMATS = [
  { value: "TV", label: "TV" },
  { value: "TV_SHORT", label: "TV Short" },
  { value: "MOVIE", label: "Film" },
  { value: "SPECIAL", label: "Özel" },
  { value: "OVA", label: "OVA" },
  { value: "ONA", label: "ONA" },
  { value: "MUSIC", label: "Müzik" },
]

export const MANGA_FORMATS = [
  { value: "MANGA", label: "Manga" },
  { value: "ONE_SHOT", label: "One Shot" },
]

export const STATUSES = [
  { value: "RELEASING", label: "Devam Ediyor" },
  { value: "FINISHED", label: "Tamamlandı" },
  { value: "NOT_YET_RELEASED", label: "Yakında" },
  { value: "HIATUS", label: "Ara Verildi" },
  { value: "CANCELLED", label: "İptal Edildi" },
]

export const SEASONS = [
  { value: "WINTER", label: "Kış" },
  { value: "SPRING", label: "İlkbahar" },
  { value: "SUMMER", label: "Yaz" },
  { value: "FALL", label: "Sonbahar" },
]

export const SORT_OPTIONS = [
  { value: "POPULARITY_DESC", label: "Popülerlik" },
  { value: "SCORE_DESC", label: "Puan" },
  { value: "TRENDING_DESC", label: "Trend" },
  { value: "FAVOURITES_DESC", label: "Favoriler" },
  { value: "START_DATE_DESC", label: "En Yeni" },
  { value: "TITLE_ROMAJI", label: "Başlık (A-Z)" },
]

export const STATUS_LABELS: Record<string, string> = {
  FINISHED: "Tamamlandı",
  RELEASING: "Devam Ediyor",
  NOT_YET_RELEASED: "Yakında",
  CANCELLED: "İptal Edildi",
  HIATUS: "Ara Verildi",
}

export const FORMAT_LABELS: Record<string, string> = {
  TV: "TV",
  TV_SHORT: "TV Short",
  MOVIE: "Film",
  SPECIAL: "Özel",
  OVA: "OVA",
  ONA: "ONA",
  MUSIC: "Müzik",
  MANGA: "Manga",
  NOVEL: "Novel",
  ONE_SHOT: "One Shot",
}

export const LIST_STATUS_META: Record<ListStatus, { label: string; forType: MediaType | "both" }> = {
  watching: { label: "İzleniyor", forType: "anime" },
  reading: { label: "Okunuyor", forType: "manga" },
  completed: { label: "Tamamlandı", forType: "both" },
  planning: { label: "Planlanıyor", forType: "both" },
  paused: { label: "Ara Verildi", forType: "both" },
  dropped: { label: "Bırakıldı", forType: "both" },
}

export function statusOptionsForType(type: MediaType): { value: ListStatus; label: string }[] {
  return (Object.entries(LIST_STATUS_META) as [ListStatus, { label: string; forType: MediaType | "both" }][])
    .filter(([, meta]) => meta.forType === "both" || meta.forType === type)
    .map(([value, meta]) => ({ value, label: meta.label }))
}

export function currentYearRange(): number[] {
  const year = new Date().getFullYear() + 1
  const years: number[] = []
  for (let y = year; y >= 1970; y--) years.push(y)
  return years
}
