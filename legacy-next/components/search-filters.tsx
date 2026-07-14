"use client"

import { X } from "lucide-react"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Button } from "@/components/ui/button"
import {
  ANIME_FORMATS,
  currentYearRange,
  GENRES,
  MANGA_FORMATS,
  SEASONS,
  SORT_OPTIONS,
  STATUSES,
} from "@/lib/constants"
import type { MediaType } from "@/lib/types"

export interface FilterState {
  genre: string
  format: string
  status: string
  season: string
  seasonYear: string
  sort: string
}

export const DEFAULT_FILTERS: FilterState = {
  genre: "all",
  format: "all",
  status: "all",
  season: "all",
  seasonYear: "all",
  sort: "POPULARITY_DESC",
}

interface Props {
  type: MediaType
  filters: FilterState
  onChange: (patch: Partial<FilterState>) => void
  onReset: () => void
}

function FilterSelect({
  label,
  value,
  onValueChange,
  children,
}: {
  label: string
  value: string
  onValueChange: (v: string) => void
  children: React.ReactNode
}) {
  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <label className="text-xs font-medium text-muted-foreground">{label}</label>
      <Select value={value} onValueChange={onValueChange}>
        <SelectTrigger size="default" className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>{children}</SelectContent>
      </Select>
    </div>
  )
}

export function SearchFilters({ type, filters, onChange, onReset }: Props) {
  const formats = type === "manga" ? MANGA_FORMATS : ANIME_FORMATS
  const years = currentYearRange()
  const hasActive =
    filters.genre !== "all" ||
    filters.format !== "all" ||
    filters.status !== "all" ||
    filters.season !== "all" ||
    filters.seasonYear !== "all" ||
    filters.sort !== "POPULARITY_DESC"

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
      <FilterSelect label="Tür" value={filters.genre} onValueChange={(v) => onChange({ genre: v })}>
        <SelectItem value="all">Tüm türler</SelectItem>
        {GENRES.map((g) => (
          <SelectItem key={g} value={g}>
            {g}
          </SelectItem>
        ))}
      </FilterSelect>

      <FilterSelect label="Format" value={filters.format} onValueChange={(v) => onChange({ format: v })}>
        <SelectItem value="all">Tüm formatlar</SelectItem>
        {formats.map((f) => (
          <SelectItem key={f.value} value={f.value}>
            {f.label}
          </SelectItem>
        ))}
      </FilterSelect>

      <FilterSelect label="Durum" value={filters.status} onValueChange={(v) => onChange({ status: v })}>
        <SelectItem value="all">Tüm durumlar</SelectItem>
        {STATUSES.map((s) => (
          <SelectItem key={s.value} value={s.value}>
            {s.label}
          </SelectItem>
        ))}
      </FilterSelect>

      {type === "anime" && (
        <FilterSelect label="Sezon" value={filters.season} onValueChange={(v) => onChange({ season: v })}>
          <SelectItem value="all">Tüm sezonlar</SelectItem>
          {SEASONS.map((s) => (
            <SelectItem key={s.value} value={s.value}>
              {s.label}
            </SelectItem>
          ))}
        </FilterSelect>
      )}

      <FilterSelect label="Yıl" value={filters.seasonYear} onValueChange={(v) => onChange({ seasonYear: v })}>
        <SelectItem value="all">Tüm yıllar</SelectItem>
        {years.map((y) => (
          <SelectItem key={y} value={String(y)}>
            {y}
          </SelectItem>
        ))}
      </FilterSelect>

      <FilterSelect label="Sırala" value={filters.sort} onValueChange={(v) => onChange({ sort: v })}>
        {SORT_OPTIONS.map((s) => (
          <SelectItem key={s.value} value={s.value}>
            {s.label}
          </SelectItem>
        ))}
      </FilterSelect>

      {hasActive && (
        <div className="col-span-2 flex items-end sm:col-span-1">
          <Button variant="ghost" size="default" onClick={onReset} className="text-muted-foreground">
            <X data-icon="inline-start" />
            Filtreleri Temizle
          </Button>
        </div>
      )}
    </div>
  )
}
