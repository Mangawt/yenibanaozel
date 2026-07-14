"use client"

import { BookmarkCheck, Check, Plus, Trash2 } from "lucide-react"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { LIST_STATUS_META, statusOptionsForType } from "@/lib/constants"
import type { ListStatus, MediaItem } from "@/lib/types"
import { useMediaList } from "@/lib/use-media-list"
import { cn } from "@/lib/utils"

interface Props {
  media: MediaItem
  size?: "sm" | "default" | "lg"
  variant?: "default" | "outline" | "secondary"
  className?: string
  fullWidth?: boolean
}

export function ListStatusSelect({ media, size = "sm", variant = "outline", className, fullWidth }: Props) {
  const { getEntry, setStatus, remove, mounted } = useMediaList()
  const entry = getEntry(media.id, media.type)
  const options = statusOptionsForType(media.type)

  function handleSet(status: ListStatus) {
    setStatus(media, status)
    toast.success(`"${media.displayTitle}" listene eklendi`, {
      description: LIST_STATUS_META[status].label,
    })
  }

  function handleRemove() {
    remove(media.id, media.type)
    toast("Listenden kaldırıldı", { description: media.displayTitle })
  }

  const inList = mounted && !!entry

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button
            size={size}
            variant={inList ? "default" : variant}
            className={cn(fullWidth && "w-full", className)}
          >
            {inList ? <BookmarkCheck data-icon="inline-start" /> : <Plus data-icon="inline-start" />}
            {inList ? LIST_STATUS_META[entry.status].label : "Listeme Ekle"}
          </Button>
        }
      />
      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuGroup>
          {options.map((opt) => (
            <DropdownMenuItem key={opt.value} onClick={() => handleSet(opt.value)}>
              {entry?.status === opt.value && <Check data-icon="inline-start" />}
              <span className={cn(entry?.status !== opt.value && "pl-6")}>{opt.label}</span>
            </DropdownMenuItem>
          ))}
        </DropdownMenuGroup>
        {inList && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
              <DropdownMenuItem variant="destructive" onClick={handleRemove}>
                <Trash2 data-icon="inline-start" />
                Listeden Kaldır
              </DropdownMenuItem>
            </DropdownMenuGroup>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
