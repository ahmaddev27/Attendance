import * as React from "react"

import { cn } from "@/lib/utils"

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<"input">>(
  ({ className, type, ...props }, ref) => {
    return (
      <input
        type={type}
        className={cn(
          // `text-start` pins text (and placeholder) to the logical
          // start of the field — right in the app's RTL shell, left in
          // any nested LTR run (numeric / date / time / email inputs
          // that carry `dir="ltr"`), so Arabic placeholders never
          // render left-anchored inside an otherwise RTL row.
          "flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base text-start shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Input.displayName = "Input"

export { Input }
