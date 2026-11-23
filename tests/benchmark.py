"""Measure local HTTP responses against the synthetic benchmark database."""
import json
from concurrent.futures import ThreadPoolExecutor
from time import perf_counter
from http_flow import Browser, PASS

CLIENTS = 4
PER_CLIENT = 15
clients = [Browser() for _ in range(CLIENTS)]
for client in clients:
    client.login("demo-bench", PASS)


def sample(client):
    values = {"personnel": [], "map": []}
    for _ in range(PER_CLIENT):
        for route in values:
            start = perf_counter()
            _, response = client.call(route, query={"dikum": 2, "dikmil": 3})
            values[route].append(1000 * (perf_counter() - start))
            if route == "map":
                assert sum(unit["total"] for unit in json.loads(response)["units"]) == 10000
            else:
                assert b"DATA DEMO Personel Ukur 00001" in response
    return values


with ThreadPoolExecutor(max_workers=CLIENTS) as pool:
    results = list(pool.map(sample, clients))
for route in ["personnel", "map"]:
    values = sorted(value for result in results for value in result[route])
    p95 = values[(95 * len(values) + 99) // 100 - 1]
    print(f"{route}: n={len(values)} concurrency={CLIENTS} p95={p95:.2f} ms")
