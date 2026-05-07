export default function Home() {
  const apiUrl =
    process.env.NEXT_PUBLIC_API_URL ?? "http://api.calvertchess.test";

  return (
    <main className="min-h-screen bg-stone-950 text-stone-50">
      <section className="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-16">
        <div className="mb-8 inline-flex w-fit rounded-full border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-medium text-amber-200">
          Local frontend scaffold is live
        </div>

        <div className="max-w-3xl">
          <p className="mb-4 text-sm font-semibold uppercase tracking-[0.3em] text-amber-300">
            Chess Coach Journal
          </p>
          <h1 className="text-4xl font-semibold tracking-tight text-white sm:text-6xl">
            Analyse your games, understand your mistakes, and track what to
            practise next.
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-8 text-stone-300">
            Paste a PGN, review the three most important moments, and turn
            engine analysis into plain-English improvement notes.
          </p>
        </div>

        <div className="mt-10 grid gap-4 sm:grid-cols-3">
          {[
            ["1", "Import PGN"],
            ["2", "Analyse key moments"],
            ["3", "Review explanations"],
          ].map(([step, label]) => (
            <div
              className="rounded-2xl border border-white/10 bg-white/5 p-5"
              key={step}
            >
              <div className="mb-4 flex size-9 items-center justify-center rounded-full bg-amber-300 text-sm font-bold text-stone-950">
                {step}
              </div>
              <p className="font-medium text-stone-100">{label}</p>
            </div>
          ))}
        </div>

        <div className="mt-10 rounded-2xl border border-white/10 bg-black/30 p-5">
          <p className="text-sm font-medium text-stone-400">API target</p>
          <code className="mt-2 block break-all text-amber-200">{apiUrl}</code>
        </div>
      </section>
    </main>
  );
}
