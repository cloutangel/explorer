<?php
namespace Muvon\KISS;

function binary_pack(array $data): string {
  return BinaryCodec::create()->pack($data);
}

function binary_unpack(string $binary): array {
  return BinaryCodec::create()->unpack($binary);
}