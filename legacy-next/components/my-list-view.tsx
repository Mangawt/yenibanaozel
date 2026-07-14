"use client"

import { Star, Trash2 } from "lucide-react"
import Image from "next/image"
import Link from "next/link"
import { useMemo, useState } from "react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group"
import { FORMAT_LABELS, LIST_STATUS_META } from "@/lib/constants"
import { formatScore } from "@/lib/format"
import type { ListEntry, ListStatus, MediaType } from "@/lib/types"
import { useMediaList } from "@/lib/use-media-list"

const STATUS_ORDER: ListStatus[] = ["watching", "reading", "completed", "planning", "paused", "dropped"]

function ListRow({ entry, onRemove }: { entry: ListEntry; onRemove: () => void }) {
  const score = formatScore(entry.averageScore)
  const format = entry.format ? (FORMAT_LABELS[entry.format] ?? entry.format) : null

  return (
    <div className="group flex items-center gap-3 rounded-lg border border-border bg-card p-2.5 transition-colors hover:border-primary/40">
      <Link
        href={`/${entry.type}/${entry.id}`}
        className="relative h-20 w-14 shrink-0 overflow-hidden rounded-md bg-muted"
        aria-label={entry.title}
      >
        {entry.coverImage ? (
          <Image
            src={entry.coverImage || "/placeholder.svg"}
            alt={`${entry.title} kapak görseli`}
            fill
            sizes="56px"
            className="object-cover"
          />
        ) : (
          <span className="flex size-full items-center justify-center p-1 text-center text-[10px] text-muted-foreground">
            {entry.title}
          </span>
        )}
      </Link>

      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <Link href={`/${entry.type}/${entry.id}`} className="line-clamp-2 text-sm font-medium leading-snug hover:text-primary">
          {entry.title}
        </Link>
        <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
          <Badge variant="secondary" className="capitalize">
            {entry.type === "anime" ? "Anime" : "Manga"}
          </Badge>
          {format && <span>{format}</span>}
          {score && (
            <span className="flex items-center gap-0.5">
              <Star className="size-3 fill-primary text-primary" />
              {score}
            </span>
          )}
        </div>
      </div>

      <Button
        variant="ghost"
        size="icon"
        aria-label={`${entry.title} listeden kaldır`}
        className="shrink-0 text-muted-foreground hover:text-destructive"
        onClick={onRemove}
      >
        <Trash2 />
      </Button>
    </div>
  )
}

export function MyListView() {
  const { entries, mounted, remove } = useMediaList()
  const [typeFilter, setTypeFilter] = useState<MediaType | "all">("all")
  const [activeStatus, setActiveStatus] = useState<ListStatus | "all">("all")

  const filtered = useMemo(() => {
    return entries
      .filter((e) => (typeFilter === "all" ? true : e.type === typeFilter))
      .filter((e) => (activeStatus === "all" ? true : e.status === activeStatus))
      .sort((a, b) => b.addedAt - a.addedAt)
  }, [entries, typeFilter, activeStatus])

  const counts = useMemo(() => {
    const scoped = entries.filter((e) => (typeFilter === "all" ? true : e.type === typeFilter))
    const map: Record<string, number> = { all: scoped.length }
    for (const s of STATUS_ORDER) map[s] = scoped.filter((e) => e.status === s).length
    return map
  }, [entries, typeFilter])

  if (!mounted) {
    return <div className="h-64" aria-hidden />
  }

  const visibleStatuses = STATUS_ORDER.filter((s) => counts[s] > 0)

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-semibold tracking-tight">Listem</h1>
          <p className="text-sm text-muted-foreground">
            {entries.length > 0 ? `${entries.length} kayıtlı eser` : "Henüz eser eklemedin"}
          </p>
        </div>
        <ToggleGroup
          value={[typeFilter]}
          onValueChange={(v) => {
            const next = (v[0] as MediaType | "all") ?? "all"
            setTypeFilter(next)
          }}
          className="w-fit"
        >
          <ToggleGroupItem value="all">Tümü</ToggleGroupItem>
          <ToggleGroupItem value="anime">Anime</ToggleGroupItem>
          <ToggleGroupItem value="manga">Manga</ToggleGroupItem>
        </ToggleGroup>
      </div>

      {entries.length === 0 ? (
        <Empty className="rounded-xl border border-dashed border-border py-16">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Star />
            </EmptyMedia>
            <EmptyTitle>Listen boş</EmptyTitle>
            <EmptyDescription>
              Bir esere göz atıp kart üzerindeki durum menüsünden listeye ekleyebilirsin.
            </EmptyDescription>
          </EmptyHeader>
          <Button render={<Link href="/">Keşfetmeye başla</Link>} />
        </Empty>
      ) : (
        <>
          <Tabs value={activeStatus} onValueChange={(v) => setActiveStatus(v as ListStatus | "all")}>
            <TabsList className="flex-wrap">
              <TabsTrigger value="all">Tümü ({counts.all})</TabsTrigger>
              {visibleStatuses.map((s) => (
                <TabsTrigger key={s} value={s}>
                  {LIST_STATUS_META[s].label} ({counts[s]})
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>

          {filtered.length === 0 ? (
            <p className="py-12 text-center text-sm text-muted-foreground">Bu filtrede eser bulunamadı.</p>
          ) : (
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              {filtered.map((entry) => (
                <ListRow
                  key={`${entry.type}:${entry.id}`}
                  entry={entry}
                  onRemove={() => remove(entry.id, entry.type)}
                />
              ))}
            </div>
          )}
        </>
      )}
    </div>
  )
}
