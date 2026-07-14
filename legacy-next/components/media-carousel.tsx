"use client"

import { ChevronLeft, ChevronRight } from "lucide-react"
import { useRef } from "react"
import { MediaCard } from "@/components/media-card"
import { Button } from "@/components/ui/button"
import type { MediaItem } from "@/lib/types"

export function MediaCarousel({ title, items }: { title: string; items: MediaItem[] }) {
  const scrollRef = useRef<HTMLDivElement>(null)

  function scroll(dir: "left" | "right") {
    const el = scrollRef.current
    if (!el) return
    el.scrollBy({ left: dir === "left" ? -el.clientWidth * 0.8 : el.clientWidth * 0.8, behavior: "smooth" })
  }

  if (!items.length) return null

  return (
    <section className="flex flex-col gap-3">
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-lg font-semibold tracking-tight text-balance">{title}</h2>
        <div className="hidden gap-1 sm:flex">
          <Button variant="outline" size="icon-sm" aria-label="Geri kaydır" onClick={() => scroll("left")}>
            <ChevronLeft />
          </Button>
          <Button variant="outline" size="icon-sm" aria-label="İleri kaydır" onClick={() => scroll("right")}>
            <ChevronRight />
          </Button>
        </div>
      </div>

      <div
        ref={scrollRef}
        className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {items.map((item) => (
          <div key={`${item.type}-${item.id}`} className="w-32 shrink-0 snap-start sm:w-40">
            <MediaCard item={item} />
          </div>
        ))}
      </div>
    </section>
  )
}
