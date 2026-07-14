"use client"

import { AlertCircle, ChevronLeft, ChevronRight, SearchX } from "lucide-react"
import { useRouter, useSearchParams } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"
import useSWR from "swr"
import { MediaGrid, MediaGridSkeleton } from "@/components/media-grid"
import { DEFAULT_FILTERS, type FilterState, SearchFilters } from "@/components/search-filters"
import { Button } from "@/components/ui/button"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { Search } from "lucide-react"
import { fetcher } from "@/lib/fetcher"
import type { MediaType, SearchResponse } from "@/lib/types"
import { cn } from "@/lib/utils"

function useDebounced<T>(value: T, delay = 400): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}

export function SearchView() {
  const router = useRouter()
  const params = useSearchParams()

  const type = (params.get("type") === "manga" ? "manga" : "anime") as MediaType
  const initialQuery = params.get("query") ?? ""

  const [query, setQuery] = useState(initialQuery)
  const [filters, setFilters] = useState<FilterState>(DEFAULT_FILTERS)
  const [page, setPage] = useState(1)

  const debouncedQuery = useDebounced(query)

  // Reset when type changes via header links
  useEffect(() => {
    setQuery(params.get("query") ?? "")
    setPage(1)
    setFilters(DEFAULT_FILTERS)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [type])

  useEffect(() => {
    setPage(1)
  }, [debouncedQuery, filters])

  const key = useMemo(() => {
    const sp = new URLSearchParams({ type, page: String(page) })
    if (debouncedQuery.trim()) sp.set("query", debouncedQuery.trim())
    if (filters.genre !== "all") sp.set("genre", filters.genre)
    if (filters.format !== "all") sp.set("format", filters.format)
    if (filters.status !== "all") sp.set("status", filters.status)
    if (filters.season !== "all") sp.set("season", filters.season)
    if (filters.seasonYear !== "all") sp.set("seasonYear", filters.seasonYear)
    if (filters.sort) sp.set("sort", filters.sort)
    return `/api/search?${sp.toString()}`
  }, [type, page, debouncedQuery, filters])

  const { data, error, isLoading } = useSWR<SearchResponse>(key, fetcher, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  })

  const setType = useCallback(
    (t: MediaType) => {
      router.push(`/search?type=${t}`)
    },
    [router],
  )

  const onFilterChange = useCallback((patch: Partial<FilterState>) => {
    setFilters((prev) => ({ ...prev, ...patch }))
  }, [])

  const items = data?.items ?? []
  const pageInfo = data?.pageInfo

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-between gap-3">
          <h1 className="text-2xl font-bold tracking-tight">Ara & Filtrele</h1>
          <div className="inline-flex w-fit rounded-lg border border-border bg-secondary/50 p-1">
            {(["anime", "manga"] as MediaType[]).map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => setType(t)}
                className={cn(
                  "rounded-md px-4 py-1.5 text-sm font-medium transition-colors",
                  type === t
                    ? "bg-background text-foreground shadow-sm"
                    : "text-muted-foreground hover:text-foreground",
                )}
              >
                {t === "anime" ? "Anime" : "Manga"}
              </button>
            ))}
          </div>
        </div>

        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={`${type === "anime" ? "Anime" : "Manga"} adı ara...`}
            aria-label="Ara"
            className="h-11 w-full rounded-lg border border-input bg-secondary/40 pl-10 pr-4 text-base outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/30"
          />
        </div>

        <SearchFilters
          type={type}
          filters={filters}
          onChange={onFilterChange}
          onReset={() => setFilters(DEFAULT_FILTERS)}
        />
      </div>

      {error ? (
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <AlertCircle />
            </EmptyMedia>
            <EmptyTitle>Bir hata oluştu</EmptyTitle>
            <EmptyDescription>Sonuçlar yüklenemedi. Lütfen tekrar deneyin.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : isLoading && !data ? (
        <MediaGridSkeleton />
      ) : items.length === 0 ? (
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <SearchX />
            </EmptyMedia>
            <EmptyTitle>Sonuç bulunamadı</EmptyTitle>
            <EmptyDescription>Farklı bir arama terimi veya filtre kombinasyonu deneyin.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <div className={cn("flex flex-col gap-6", isLoading && "opacity-60")}>
          <MediaGrid items={items} />

          {pageInfo && (pageInfo.hasNextPage || page > 1) && (
            <div className="flex items-center justify-center gap-3 pt-2">
              <Button
                variant="outline"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                <ChevronLeft data-icon="inline-start" />
                Önceki
              </Button>
              <span className="text-sm text-muted-foreground">
                Sayfa {pageInfo.currentPage}
                {pageInfo.lastPage ? ` / ${pageInfo.lastPage}` : ""}
              </span>
              <Button variant="outline" disabled={!pageInfo.hasNextPage} onClick={() => setPage((p) => p + 1)}>
                Sonraki
                <ChevronRight data-icon="inline-end" />
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
