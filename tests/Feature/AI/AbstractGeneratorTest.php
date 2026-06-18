<?php

use App\Services\AI\AbstractGenerator;
use App\Services\AI\FlashcardsGenerator;
use App\Services\AI\ResumoGenerator;
use App\Services\AI\SimuladoGenerator;

it('SimuladoGenerator estende AbstractGenerator', function () {
    expect(SimuladoGenerator::class)->toExtend(AbstractGenerator::class);
});

it('ResumoGenerator estende AbstractGenerator', function () {
    expect(ResumoGenerator::class)->toExtend(AbstractGenerator::class);
});

it('FlashcardsGenerator estende AbstractGenerator', function () {
    expect(FlashcardsGenerator::class)->toExtend(AbstractGenerator::class);
});
