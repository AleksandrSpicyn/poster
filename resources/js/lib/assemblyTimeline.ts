interface AssemblyPart {
    number: number;
    assembly_id: number;
    duration_ms: number;
}

export function assemblyTimeline(parts: AssemblyPart[], versions: Record<number, number>) {
    let offset = 0;
    return parts.map((part) => {
        const start = offset;
        offset += part.duration_ms;
        return { ...part, start, version: versions?.[part.assembly_id] };
    });
}
