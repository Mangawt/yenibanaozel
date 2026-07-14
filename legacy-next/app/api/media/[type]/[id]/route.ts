import { type NextRequest, NextResponse } from "next/server"
import { getMediaDetails } from "@/lib/anilist"
import type { MediaType } from "@/lib/types"

export async function GET(_req: NextRequest, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type: rawType, id } = await params
  const type = (rawType === "manga" ? "manga" : "anime") as MediaType
  const numericId = Number(id)

  if (!Number.isFinite(numericId)) {
    return NextResponse.json({ error: "Geçersiz ID." }, { status: 400 })
  }

  try {
    const data = await getMediaDetails(numericId, type)
    return NextResponse.json(data)
  } catch (err) {
    console.log("[v0] Media details failed:", (err as Error).message)
    return NextResponse.json({ error: "Detaylar yüklenemedi." }, { status: 502 })
  }
}
