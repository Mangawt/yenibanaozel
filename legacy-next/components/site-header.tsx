"use client"

import { Bookmark, Library, Search } from "lucide-react"
import Link from "next/link"
import { usePathname, useRouter } from "next/navigation"
import { useState } from "react"
import { ThemeToggle } from "@/components/theme-toggle"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const NAV = [
  { href: "/", label: "Keşfet" },
  { href: "/search?type=anime", label: "Anime", match: "/search" },
  { href: "/search?type=manga", label: "Manga" },
  { href: "/my-list", label: "Listem" },
]

export function SiteHeader() {
  const pathname = usePathname()
  const router = useRouter()
  const [q, setQ] = useState("")

  function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    const query = q.trim()
    router.push(`/search?type=anime${query ? `&query=${encodeURIComponent(query)}` : ""}`)
  }

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-background/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        <Link href="/" className="flex items-center gap-2 font-bold text-lg tracking-tight">
          <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
            <Library className="size-5" />
          </span>
          <span className="hidden sm:inline">AniDex</span>
        </Link>

        <nav className="hidden items-center gap-1 md:flex">
          {NAV.map((item) => {
            const active =
              item.href === "/"
                ? pathname === "/"
                : pathname.startsWith(item.match ?? item.href.split("?")[0])
            return (
              <Button
                key={item.label}
                variant="ghost"
                size="sm"
                className={cn("text-muted-foreground", active && "text-foreground")}
                render={<Link href={item.href}>{item.label}</Link>}
              />
            )
          })}
        </nav>

        <form onSubmit={onSubmit} className="ml-auto flex max-w-xs flex-1 items-center">
          <div className="relative w-full">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <input
              type="search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Anime veya manga ara..."
              aria-label="Ara"
              className="h-9 w-full rounded-md border border-input bg-secondary/50 pl-9 pr-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/30"
            />
          </div>
        </form>

        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon"
            className="md:hidden"
            aria-label="Listem"
            render={
              <Link href="/my-list">
                <Bookmark />
              </Link>
            }
          />
          <ThemeToggle />
        </div>
      </div>
    </header>
  )
}
