import * as React from "react"

import { cn } from "@/lib/utils"

const Textarea = React.forwardRef<HTMLTextAreaElement, React.ComponentProps<"textarea">>(
  ({ className, ...props }, ref) => {
    return (
      <textarea
        className={cn(
          // See input.tsx for rationale — `text-start` keeps
          // placeholder + typed text anchored to the logical start
          // (right in the RTL shell, left inside a `dir="ltr"` field).
          "flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base text-start shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Textarea.displayName = "Textarea"

export { Textarea }
