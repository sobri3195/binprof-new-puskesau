"""Extract a simplified regional outline from the pinned Natural Earth package."""
import json
from pathlib import Path

root = Path(__file__).resolve().parent
topology = json.loads((root / "node_modules/world-atlas/countries-110m.json").read_text())
scale = topology["transform"]["scale"]
translate = topology["transform"]["translate"]
region = {"360", "458", "608", "036", "598", "626", "096", "704", "764", "104",
          "116", "418", "156", "356", "050", "144", "762", "586", "554"}


def arc(index):
    x = y = 0
    positions = []
    for dx, dy in topology["arcs"][index if index >= 0 else ~index]:
        x += dx
        y += dy
        positions.append([round(x * scale[0] + translate[0], 4),
                          round(y * scale[1] + translate[1], 4)])
    return positions if index >= 0 else positions[::-1]


def ring(indices):
    positions = []
    for index in indices:
        positions.extend(arc(index)[1 if positions else 0:])
    return positions


features = []
for geometry in topology["objects"]["countries"]["geometries"]:
    if geometry.get("id") not in region:
        continue
    polygons = geometry["arcs"] if geometry["type"] == "MultiPolygon" else [geometry["arcs"]]
    features.append({
        "type": "Feature",
        "properties": {},
        "geometry": {"type": "MultiPolygon",
                     "coordinates": [[ring(indices) for indices in polygon] for polygon in polygons]},
    })
output = root.parent / "public/assets/region.geojson"
output.write_text(json.dumps({"type": "FeatureCollection", "features": features},
                            separators=(",", ":")) + "\n")
