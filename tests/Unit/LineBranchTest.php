<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use GtfsTimetables\Network\LineBranch;
use GtfsTimetables\Network\LineDirection;
use GtfsTimetables\Network\LineStop;
use PHPUnit\Framework\TestCase;

final class LineBranchTest extends TestCase {

	private const LAST_POSITION = 3;

	public function test_each_branch_knows_how_it_relates_to_the_route(): void {
		$this->assertSame( LineBranch::STARTS_BEFORE, $this->branch( -1, 0 )->kind( self::LAST_POSITION ) );
		$this->assertSame( LineBranch::JOINS, $this->branch( -1, 2 )->kind( self::LAST_POSITION ) );
		$this->assertSame( LineBranch::DETOUR, $this->branch( 1, 2 )->kind( self::LAST_POSITION ) );
		$this->assertSame( LineBranch::ENDS, $this->branch( 1, null )->kind( self::LAST_POSITION ) );
		$this->assertSame( LineBranch::CONTINUES, $this->branch( self::LAST_POSITION, null )->kind( self::LAST_POSITION ) );
	}

	/**
	 * Line 21 in Gdynia: some trips leave the route after 3 Maja 02 and end at Gdynia Dworzec Gł. PKP – Morska 02.
	 */
	public function test_trips_end_at_the_end_of_a_branch_that_does_not_come_back(): void {
		$direction = new LineDirection(
			0,
			'Gdynia Dworzec Gł. PKP',
			array( new LineStop( 'reja', 'Sopot Reja 01' ), new LineStop( '3maja', '3 Maja 02' ), new LineStop( 'pkp', 'Gdynia Dworzec Gł. PKP 09' ) ),
			array( new LineBranch( 1, null, array( new LineStop( 'morska', 'Gdynia Dworzec Gł. PKP – Morska 02' ) ) ) )
		);

		$this->assertTrue( $direction->ends_at( 'morska' ) );
		$this->assertTrue( $direction->ends_at( 'pkp' ) );
		$this->assertFalse( $direction->ends_at( '3maja' ) );
		$this->assertSame( LineBranch::ENDS, $direction->kind_of( $direction->branches[0] ) );
	}

	private function branch( int $leaves_after, ?int $rejoins_at ): LineBranch {
		return new LineBranch( $leaves_after, $rejoins_at, array( new LineStop( 'x', 'X' ) ) );
	}
}
