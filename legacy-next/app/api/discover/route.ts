import { type NextRequest, NextResponse } from "next/server"
import { getDiscover } from "@/lib/anilist"
import type { MediaType } from "@/lib/types"

export async function GET(req: NextRequest) {
  const type = (req.nextUrl.searchParams.get("type") === "manga" ? "manga" : "anime") as MediaType
  try {
    const data = await getDiscover(type)
    return NextResponse.json(data)
  } catch (err) {
    console.log("[v0] Discover failed:", (err as Error).message)
    return NextResponse.json({ error: "İçerik yüklenemedi." }, { status: 502 })
  }
}
