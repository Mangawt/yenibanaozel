"use client"

import { AlertCircle, Search, Star, TrendingUp } from "lucide-react"
import Image from "next/image"
import Link from "next/link"
import { useState } from "react"
import useSWR from "swr"
import { MediaCarousel } from "@/components/media-carousel"
import { CarouselSkeleton } from "@/components/media-grid"
import { Button } from "@/components/ui/button"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import type { DiscoverData } from "@/lib/anilist"
import { formatScore, metaLine, stripHtml } from "@/lib/format"
import { fetcher } from "@/lib/fetcher"
import type { MediaItem, MediaType } from "@/lib/types"
import { cn } from "@/lib/utils"

function Hero({ item }: { item: MediaItem }) {
  const score = formatScore(item.averageScore)
  return (
    <div className="relative overflow-hidden rounded-2xl border border-border">
      <div className="absolute inset-0">
        {item.bannerImage ? (
          <Image
            src={item.bannerImage || "/placeholder.svg"}
            alt=""
            fill
            priority
            sizes="100vw"
            className="object-cover"
          />
        ) : (
          <div className="size-full bg-secondary" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/85 to-background/40" />
        <div className="absolute inset-0 bg-gradient-to-r from-background/90 to-transparent" />
      </div>

      <div className="relative flex flex-col gap-4 p-5 sm:flex-row sm:items-end sm:p-8">
        <div className="relative hidden aspect-[2/3] w-32 shrink-0 overflow-hidden rounded-lg border border-border shadow-lg sm:block">
          {item.coverImage && (
            <Image src={item.coverImage || "/placeholder.svg"} alt="" fill sizes="128px" className="object-cover" />
          )}
        </div>

        <div className="flex flex-col gap-3">
          <div className="flex items-center gap-2 text-xs font-medium text-primary">
            <TrendingUp className="size-4" />
            Şu An Trend
          </div>
          <h1 className="max-w-2xl text-balance text-2xl font-bold tracking-tight sm:text-4xl">
            {item.displayTitle}
          </h1>
          <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
            {score && (
              <span className="flex items-center gap-1 font-semibold text-foreground">
                <Star className="size-4 fill-primary text-primary" />
                {score}
              </span>
            )}
            <span>{metaLine(item)}</span>
            {item.genres.slice(0, 3).map((g) => (
              <span key={g} className="rounded-full bg-secondary px-2 py-0.5 text-xs">
                {g}
              </span>
            ))}
          </div>
          <p className="hidden max-w-2xl text-pretty text-sm leading-relaxed text-muted-foreground sm:line-clamp-2">
            {stripHtml(item.description)}
          </p>
          <div>
            <Button render={<Link href={`/${item.type}/${item.id}`}>Detayları Gör</Link>} />
          </div>
        </div>
      </div>
    </div>
  )
}

export function DiscoverView() {
  const [type, setType] = useState<MediaType>("anime")
  const { data, error, isLoading } = useSWR<DiscoverData>(`/api/discover?type=${type}`, fetcher, {
    revalidateOnFocus: false,
  })

  const hero = data?.trending?.[0]

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-bold tracking-tight">Keşfet</h1>
          <p className="text-sm text-muted-foreground">
            Trend, popüler ve en yüksek puanlı {type === "anime" ? "animeleri" : "mangaları"} keşfet.
          </p>
        </div>
        <div className="inline-flex w-fit rounded-lg border border-border bg-secondary/50 p-1">
          {(["anime", "manga"] as MediaType[]).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setType(t)}
              className={cn(
                "rounded-md px-4 py-1.5 text-sm font-medium transition-colors",
                type === t ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
              )}
            >
              {t === "anime" ? "Anime" : "Manga"}
            </button>
          ))}
        </div>
      </div>

      {error ? (
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <AlertCircle />
            </EmptyMedia>
            <EmptyTitle>İçerik yüklenemedi</EmptyTitle>
            <EmptyDescription>
              Sunucuya ulaşırken bir sorun oluştu. Lütfen biraz sonra tekrar deneyin.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : isLoading || !data ? (
        <div className="flex flex-col gap-8">
          <div className="h-56 w-full animate-pulse rounded-2xl bg-muted sm:h-72" />
          <CarouselSkeleton title="Trend" />
          <CarouselSkeleton title="Popüler" />
        </div>
      ) : (
        <div className="flex flex-col gap-10">
          {hero && <Hero item={hero} />}
          <MediaCarousel title="Şu An Trend" items={data.trending} />
          {type === "anime" && data.seasonal.length > 0 && (
            <MediaCarousel title="Bu Sezon" items={data.seasonal} />
          )}
          <MediaCarousel title="En Popüler" items={data.popular} />
          <MediaCarousel title="En Yüksek Puanlı" items={data.top} />

          <div className="flex items-center justify-center py-4">
            <Button
              variant="outline"
              render={
                <Link href={`/search?type=${type}`}>
                  <Search data-icon="inline-start" />
                  Tümünü Ara ve Filtrele
                </Link>
              }
            />
          </div>
        </div>
      )}
    </div>
  )
}
