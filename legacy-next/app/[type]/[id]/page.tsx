import { Calendar, Heart, Layers, Star, TrendingUp, Tv } from "lucide-react"
import type { Metadata } from "next"
import Image from "next/image"
import Link from "next/link"
import { notFound } from "next/navigation"
import { CharacterRail } from "@/components/character-rail"
import { ListStatusSelect } from "@/components/list-status-select"
import { MediaCarousel } from "@/components/media-carousel"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { getMediaDetails } from "@/lib/anilist"
import { formatLabel, formatNumber, formatScore, statusLabel, stripHtml } from "@/lib/format"
import type { MediaType } from "@/lib/types"

type Params = Promise<{ type: string; id: string }>

function resolveType(raw: string): MediaType {
  return raw === "manga" ? "manga" : "anime"
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { type, id } = await params
  try {
    const item = await getMediaDetails(Number(id), resolveType(type))
    return {
      title: `${item.displayTitle} — AniDex`,
      description: stripHtml(item.description).slice(0, 160) || `${item.displayTitle} detayları`,
    }
  } catch {
    return { title: "AniDex" }
  }
}

function Stat({ icon: Icon, label, value }: { icon: typeof Star; label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1 rounded-lg border border-border bg-card p-3">
      <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Icon className="size-3.5" />
        {label}
      </span>
      <span className="text-sm font-semibold">{value}</span>
    </div>
  )
}

export default async function MediaDetailPage({ params }: { params: Params }) {
  const { type: rawType, id } = await params
  const type = resolveType(rawType)
  const numericId = Number(id)

  if (!Number.isFinite(numericId)) notFound()

  let item
  try {
    item = await getMediaDetails(numericId, type)
  } catch {
    notFound()
  }

  const score = formatScore(item.averageScore)
  const description = stripHtml(item.description)
  const year = item.seasonYear ?? item.startYear

  return (
    <main>
      {/* Banner */}
      <div className="relative h-44 w-full overflow-hidden sm:h-64 md:h-80">
        {item.bannerImage ? (
          <Image src={item.bannerImage || "/placeholder.svg"} alt="" fill priority sizes="100vw" className="object-cover" />
        ) : (
          <div className="size-full bg-gradient-to-br from-secondary to-muted" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/40 to-transparent" />
      </div>

      <div className="mx-auto max-w-7xl px-4 sm:px-6">
        <div className="relative -mt-20 flex flex-col gap-6 sm:-mt-28 sm:flex-row sm:items-end">
          {/* Cover */}
          <div className="relative aspect-[2/3] w-32 shrink-0 overflow-hidden rounded-xl border border-border bg-muted shadow-xl sm:w-48">
            {item.coverImage ? (
              <Image src={item.coverImage || "/placeholder.svg"} alt={`${item.displayTitle} kapak`} fill sizes="192px" className="object-cover" />
            ) : (
              <div className="flex size-full items-center justify-center p-2 text-center text-xs text-muted-foreground">
                {item.displayTitle}
              </div>
            )}
          </div>

          <div className="flex flex-1 flex-col gap-3 pb-1">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary">{type === "anime" ? "Anime" : "Manga"}</Badge>
              <Badge variant="outline">{formatLabel(item.format)}</Badge>
              <Badge variant="outline">{statusLabel(item.status)}</Badge>
            </div>
            <h1 className="text-balance text-2xl font-bold tracking-tight sm:text-4xl">{item.displayTitle}</h1>
            {item.title.native && item.title.native !== item.displayTitle && (
              <p className="text-sm text-muted-foreground">{item.title.native}</p>
            )}
            <div className="mt-1 flex w-full max-w-xs">
              <ListStatusSelect media={item} size="lg" variant="default" fullWidth />
            </div>
          </div>
        </div>

        {/* Stats */}
        <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {score && <Stat icon={Star} label="Puan" value={`${score} / 10`} />}
          {item.popularity != null && <Stat icon={TrendingUp} label="Popülerlik" value={`#${formatNumber(item.popularity)}`} />}
          {item.favourites != null && <Stat icon={Heart} label="Favori" value={formatNumber(item.favourites)} />}
          {type === "anime" && item.episodes != null && <Stat icon={Tv} label="Bölüm" value={String(item.episodes)} />}
          {type === "manga" && item.chapters != null && <Stat icon={Layers} label="Bölüm" value={String(item.chapters)} />}
          {type === "manga" && item.volumes != null && <Stat icon={Layers} label="Cilt" value={String(item.volumes)} />}
          {year && <Stat icon={Calendar} label="Yıl" value={String(year)} />}
        </div>

        <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_280px]">
          {/* Main content */}
          <div className="flex flex-col gap-8 lg:order-1">
            {description && (
              <section className="flex flex-col gap-3">
                <h2 className="text-lg font-semibold">Özet</h2>
                <p className="whitespace-pre-line text-pretty text-sm leading-relaxed text-muted-foreground">
                  {description}
                </p>
              </section>
            )}

            {item.characters && item.characters.length > 0 && <CharacterRail characters={item.characters} />}

            {item.relations && item.relations.length > 0 && (
              <section className="flex flex-col gap-3">
                <h2 className="text-lg font-semibold">İlişkili Eserler</h2>
                <div className="grid gap-3 sm:grid-cols-2">
                  {item.relations.slice(0, 8).map((rel) => (
                    <Link
                      key={`${rel.type}-${rel.id}`}
                      href={`/${rel.type}/${rel.id}`}
                      className="flex items-center gap-3 rounded-lg border border-border bg-card p-2 transition-colors hover:border-primary/40 hover:bg-accent/40"
                    >
                      <div className="relative aspect-[2/3] h-16 shrink-0 overflow-hidden rounded-md bg-muted">
                        {rel.coverImage && (
                          <Image src={rel.coverImage || "/placeholder.svg"} alt="" fill sizes="48px" className="object-cover" />
                        )}
                      </div>
                      <div className="flex min-w-0 flex-col gap-0.5">
                        <span className="text-xs font-medium text-primary">{rel.relationType}</span>
                        <span className="line-clamp-2 text-sm font-medium">{rel.title}</span>
                        <span className="text-xs text-muted-foreground">{formatLabel(rel.format)}</span>
                      </div>
                    </Link>
                  ))}
                </div>
              </section>
            )}
          </div>

          {/* Sidebar */}
          <aside className="flex flex-col gap-4 lg:order-2">
            <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
              <InfoRow label="Format" value={formatLabel(item.format)} />
              <Separator />
              <InfoRow label="Durum" value={statusLabel(item.status)} />
              {item.season && item.seasonYear && (
                <>
                  <Separator />
                  <InfoRow label="Sezon" value={`${item.season} ${item.seasonYear}`} />
                </>
              )}
              {item.studios.length > 0 && (
                <>
                  <Separator />
                  <InfoRow label="Stüdyo" value={item.studios.join(", ")} />
                </>
              )}
              {item.authors.length > 0 && (
                <>
                  <Separator />
                  <InfoRow label="Yazar / Çizer" value={item.authors.join(", ")} />
                </>
              )}
            </div>

            {item.genres.length > 0 && (
              <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-4">
                <h3 className="text-sm font-semibold">Türler</h3>
                <div className="flex flex-wrap gap-1.5">
                  {item.genres.map((g) => (
                    <Link key={g} href={`/search?type=${type}&genre=${encodeURIComponent(g)}`}>
                      <Badge variant="secondary" className="hover:bg-accent">
                        {g}
                      </Badge>
                    </Link>
                  ))}
                </div>
              </div>
            )}
          </aside>
        </div>

        {item.recommendations && item.recommendations.length > 0 && (
          <div className="mt-12 pb-4">
            <MediaCarousel title="Bunları da Beğenebilirsin" items={item.recommendations} />
          </div>
        )}

        <div className="h-8" />
      </div>
    </main>
  )
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs text-muted-foreground">{label}</span>
      <span className="text-sm font-medium text-pretty">{value}</span>
    </div>
  )
}
