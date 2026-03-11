<?php

function cart_normalize_ids(array $ids): array
{
  $normalized = [];

  foreach ($ids as $id) {
    $id = (int) $id;
    if ($id <= 0 || in_array($id, $normalized, true)) {
      continue;
    }
    $normalized[] = $id;
  }

  return $normalized;
}

function cart_bootstrap(): void
{
  if (!isset($_SESSION['cart_listing_ids']) || !is_array($_SESSION['cart_listing_ids'])) {
    $_SESSION['cart_listing_ids'] = [];
    return;
  }

  $_SESSION['cart_listing_ids'] = cart_normalize_ids($_SESSION['cart_listing_ids']);
}

function cart_get_ids(): array
{
  cart_bootstrap();
  return $_SESSION['cart_listing_ids'];
}

function cart_has(int $listingId): bool
{
  return in_array($listingId, cart_get_ids(), true);
}

function cart_count(): int
{
  return count(cart_get_ids());
}

function cart_add(int $listingId): bool
{
  if ($listingId <= 0) {
    return false;
  }

  $ids = cart_get_ids();
  if (in_array($listingId, $ids, true)) {
    return false;
  }

  array_unshift($ids, $listingId);
  $_SESSION['cart_listing_ids'] = $ids;

  return true;
}

function cart_remove(int $listingId): bool
{
  $ids = cart_get_ids();
  $filtered = array_values(array_filter($ids, static fn($id) => (int) $id !== $listingId));

  if (count($filtered) === count($ids)) {
    return false;
  }

  $_SESSION['cart_listing_ids'] = $filtered;
  return true;
}

function cart_toggle(int $listingId): bool
{
  if (cart_has($listingId)) {
    cart_remove($listingId);
    return false;
  }

  cart_add($listingId);
  return true;
}

function cart_clear(): void
{
  $_SESSION['cart_listing_ids'] = [];
}

