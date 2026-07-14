export async function fetcher<T>(url: string): Promise<T> {
  const res = await fetch(url)
  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw new Error(body.error || "İstek başarısız oldu.")
  }
  return res.json() as Promise<T>
}
