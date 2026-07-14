import { type NextRequest, NextResponse } from "next/server"
import { searchMedia } from "@/lib/anilist"
import { jikanSearch } from "@/lib/jikan"
import type { MediaSeason, MediaType, SearchParams } from "@/lib/types"

export async function GET(req: NextRequest) {
  const sp = req.nextUrl.searchParams
  const type = (sp.get("type") === "manga" ? "manga" : "anime") as MediaType

  const params: SearchParams = {
    type,
    query: sp.get("query") ?? undefined,
    genre: sp.get("genre") ?? undefined,
    format: sp.get("format") ?? undefined,
    season: (sp.get("season") as MediaSeason) ?? undefined,
    seasonYear: sp.get("seasonYear") ? Number(sp.get("seasonYear")) : undefined,
    status: sp.get("status") ?? undefined,
    sort: sp.get("sort") ?? undefined,
    page: sp.get("page") ? Number(sp.get("page")) : 1,
  }

  try {
    const data = await searchMedia(params)
    return NextResponse.json(data)
  } catch (err) {
    console.log("[v0] AniList search failed, falling back to Jikan:", (err as Error).message)
    try {
      const data = await jikanSearch(params.query ?? "", type, params.page ?? 1)
      return NextResponse.json(data)
    } catch (fallbackErr) {
      console.log("[v0] Jikan search failed:", (fallbackErr as Error).message)
      return NextResponse.json({ error: "Arama sırasında bir hata oluştu." }, { status: 502 })
    }
  }
}
