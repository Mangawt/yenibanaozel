import { Suspense } from "react"
import { MediaGridSkeleton } from "@/components/media-grid"
import { SearchView } from "@/components/search-view"

export default function SearchPage() {
  return (
    <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
      <Suspense fallback={<MediaGridSkeleton />}>
        <SearchView />
      </Suspense>
    </main>
  )
}
