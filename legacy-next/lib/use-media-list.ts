"use client"

import { useCallback, useEffect, useState } from "react"
import type { ListEntry, ListStatus, MediaItem, MediaType } from "./types"

const STORAGE_KEY = "anidex:list:v1"
const EVENT = "anidex:list-changed"

function readStore(): ListEntry[] {
  if (typeof window === "undefined") return []
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as ListEntry[]) : []
  } catch {
    return []
  }
}

function writeStore(entries: ListEntry[]) {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(entries))
  window.dispatchEvent(new Event(EVENT))
}

function keyOf(id: number, type: MediaType) {
  return `${type}:${id}`
}

export function useMediaList() {
  const [entries, setEntries] = useState<ListEntry[]>([])
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setEntries(readStore())
    setMounted(true)

    const sync = () => setEntries(readStore())
    window.addEventListener(EVENT, sync)
    window.addEventListener("storage", sync)
    return () => {
      window.removeEventListener(EVENT, sync)
      window.removeEventListener("storage", sync)
    }
  }, [])

  const getEntry = useCallback(
    (id: number, type: MediaType): ListEntry | undefined =>
      entries.find((e) => keyOf(e.id, e.type) === keyOf(id, type)),
    [entries],
  )

  const setStatus = useCallback((media: MediaItem, status: ListStatus) => {
    const current = readStore()
    const k = keyOf(media.id, media.type)
    const existing = current.find((e) => keyOf(e.id, e.type) === k)
    const entry: ListEntry = {
      id: media.id,
      type: media.type,
      title: media.displayTitle,
      coverImage: media.coverImage ?? null,
      format: media.format ?? null,
      status,
      averageScore: media.averageScore ?? null,
      addedAt: existing?.addedAt ?? Date.now(),
    }
    const next = existing
      ? current.map((e) => (keyOf(e.id, e.type) === k ? entry : e))
      : [entry, ...current]
    writeStore(next)
  }, [])

  const remove = useCallback((id: number, type: MediaType) => {
    const current = readStore()
    writeStore(current.filter((e) => keyOf(e.id, e.type) !== keyOf(id, type)))
  }, [])

  return { entries, mounted, getEntry, setStatus, remove }
}
