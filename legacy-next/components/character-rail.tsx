import Image from "next/image"
import type { MediaCharacter } from "@/lib/types"

const ROLE_LABELS: Record<string, string> = {
  MAIN: "Başrol",
  SUPPORTING: "Yan Rol",
  BACKGROUND: "Arka Plan",
}

export function CharacterRail({ characters }: { characters: MediaCharacter[] }) {
  return (
    <section className="flex flex-col gap-3">
      <h2 className="text-lg font-semibold">Karakterler</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        {characters.map((c) => (
          <div
            key={c.id}
            className="flex items-center gap-3 rounded-lg border border-border bg-card p-2"
          >
            <div className="relative size-14 shrink-0 overflow-hidden rounded-md bg-muted">
              {c.image && <Image src={c.image || "/placeholder.svg"} alt={c.name} fill sizes="56px" className="object-cover" />}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
              <span className="line-clamp-1 text-sm font-medium">{c.name}</span>
              {c.role && <span className="text-xs text-muted-foreground">{ROLE_LABELS[c.role] ?? c.role}</span>}
            </div>
            {c.voiceActor && (
              <span className="line-clamp-1 max-w-28 text-right text-xs text-muted-foreground">{c.voiceActor}</span>
            )}
          </div>
        ))}
      </div>
    </section>
  )
}
