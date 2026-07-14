import { Star } from "lucide-react"
import Image from "next/image"
import Link from "next/link"
import { ListStatusSelect } from "@/components/list-status-select"
import { formatScore, metaLine } from "@/lib/format"
import type { MediaItem } from "@/lib/types"

export function MediaCard({ item }: { item: MediaItem }) {
  const score = formatScore(item.averageScore)

  return (
    <div className="group relative flex flex-col gap-2">
      <div className="relative aspect-[2/3] overflow-hidden rounded-lg border border-border bg-muted">
        <Link href={`/${item.type}/${item.id}`} className="block size-full" aria-label={item.displayTitle}>
          {item.coverImage ? (
            <Image
              src={item.coverImage || "/placeholder.svg"}
              alt={`${item.displayTitle} kapak görseli`}
              fill
              sizes="(max-width: 640px) 40vw, (max-width: 1024px) 22vw, 180px"
              className="object-cover transition-transform duration-300 group-hover:scale-105"
            />
          ) : (
            <div className="flex size-full items-center justify-center p-2 text-center text-xs text-muted-foreground">
              {item.displayTitle}
            </div>
          )}
          <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
        </Link>

        {score && (
          <div className="pointer-events-none absolute left-2 top-2 flex items-center gap-1 rounded-md bg-background/90 px-1.5 py-0.5 text-xs font-semibold shadow-sm backdrop-blur">
            <Star className="size-3 fill-primary text-primary" />
            {score}
          </div>
        )}

        <div className="absolute inset-x-2 bottom-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 focus-within:opacity-100">
          <ListStatusSelect media={item} fullWidth size="sm" variant="secondary" />
        </div>
      </div>

      <div className="flex flex-col gap-0.5">
        <Link
          href={`/${item.type}/${item.id}`}
          className="line-clamp-2 text-sm font-medium leading-snug hover:text-primary"
        >
          {item.displayTitle}
        </Link>
        <p className="text-xs text-muted-foreground">{metaLine(item)}</p>
      </div>
    </div>
  )
}
