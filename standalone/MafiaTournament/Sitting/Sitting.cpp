// Sitting.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include <stdlib.h>
#include <time.h>

#define OUTPUT_TO_NUMBERS

//#define EVEN_TABLES
#define PLAYER_COUNT 30
#define GAMES_PER_PLAYER 7
#define TABLE_COUNT 2

#define ROUND_COUNT ((PLAYER_COUNT * GAMES_PER_PLAYER + 10 * TABLE_COUNT - 1) / (10 * TABLE_COUNT))
#define MIN_TABLE_FREQ (GAMES_PER_PLAYER / TABLE_COUNT)
#define MAX_TABLE_FREQ ((GAMES_PER_PLAYER + TABLE_COUNT - 1) / TABLE_COUNT)
#define MIN_PAIR_FREQ ((ROUND_COUNT * 9) / PLAYER_COUNT)
#define MAX_PAIR_FREQ (MIN_PAIR_FREQ + 3)

bool canDoSameRound = true;
bool canDoDiffRound = true;

int currentSittings[PLAYER_COUNT][ROUND_COUNT];
int currentFreq[ROUND_COUNT + 1];

int finalSittings[PLAYER_COUNT][ROUND_COUNT];
int finalFreq[ROUND_COUNT + 1];

void printSittings()
{
	printf("-------------------------------------------------------------------\n");
	for (int p = 0; p < PLAYER_COUNT; ++p)
	{
#ifdef OUTPUT_TO_NUMBERS
		if (p == 0)
		{
			printf("{ ");
		}
		else
		{
			printf(",\n{ ");
		}
		for (int r = 0; r < ROUND_COUNT - 1; ++r)
		{
			printf("%i, ", finalSittings[p][r]);
		}
		printf("%i }", finalSittings[p][ROUND_COUNT - 1]);
#else
		for (int r = 0; r < ROUND_COUNT - 1; ++r)
		{
			if (finalSittings[p][r] > 0)
			{
				printf("%i,,", finalSittings[p][r]);
			}
			else
			{
				printf(",,");
			}
		}
		if (finalSittings[p][ROUND_COUNT - 1] > 0)
		{
			printf("%i,\n", finalSittings[p][ROUND_COUNT - 1]);
		}
		else
		{
			printf(",\n");
		}
#endif
	}

	const int* freq = finalFreq;
	printf("\n.......................\n%i", *freq);
	++freq;
	for (int i = 0; i < ROUND_COUNT; ++i,++freq)
	{
		printf(", %i", *freq);
	}
	printf("\n");
}

void finalizeCurrentSittings()
{
	memcpy(finalSittings, currentSittings, PLAYER_COUNT * ROUND_COUNT * sizeof(int));
	memcpy(finalFreq, currentFreq, (ROUND_COUNT + 1) * sizeof(int));
	printSittings();
}

void calculateTableFreq(int player, int* tables)
{
	memset(tables, 0, TABLE_COUNT * sizeof(int));
	for (int r = 0; r < ROUND_COUNT; ++r)
	{
		if (currentSittings[player][r] > 0)
		{
			++tables[currentSittings[player][r] - 1];
		}
	}
}

bool isSittingValid(int player, int round, int table)
{
#ifdef EVEN_TABLES
	int tables[TABLE_COUNT];
	calculateTableFreq(player, tables);
	if (currentSittings[player][round] > 0)
	{
		--tables[currentSittings[player][round] - 1];
	}
	if (table > 0)
	{
		++tables[table - 1];
	}
	for (int i = 0; i < TABLE_COUNT; ++i)
	{
		if (tables[i] < MIN_TABLE_FREQ || tables[i] > MAX_TABLE_FREQ)
		{
			return false;
		}
	}
#endif
	return true;
}

void calcuatePairFrequences(int* frequences)
{
	for (int i = 0; i <= ROUND_COUNT; ++i)
	{
		frequences[i] = 0;
	}

	for (int p1 = 0; p1 < PLAYER_COUNT; ++p1)
	{
		for (int p2 = 0; p2 < p1; ++p2)
		{
			int count = 0;
			for (int r = 0; r < ROUND_COUNT; ++r)
			{
				if (currentSittings[p1][r] == currentSittings[p2][r] && currentSittings[p1][r] > 0)
				{
					++count;
				}
			}
			++frequences[count];
		}
	}
}

bool is1Better(const int* frequences1, const int* frequences2)
{
	int min = 0;
	while (min < ROUND_COUNT && frequences1[min] == 0 && frequences2[min] == 0)
	{
		++min;
	}

	int max = ROUND_COUNT;
	while (max > 0 && frequences1[max] == 0 && frequences2[max] == 0)
	{
		--max;
	}

	while (min < MIN_PAIR_FREQ || max > MAX_PAIR_FREQ)
	{
		if (frequences1[max] > frequences2[max] || frequences1[min] > frequences2[min])
		{
			return false;
		}

		if (frequences1[max] < frequences2[max] || frequences1[min] < frequences2[min])
		{
			return true;
		}

		if (min < MIN_PAIR_FREQ)
		{
			++min;
		}

		if (max > MIN_PAIR_FREQ)
		{
			--max;
		}
	}

	while (min < max)
	{
		int f1 = frequences1[max] + frequences1[min];
		int f2 = frequences2[max] + frequences2[min];
		if (f1 != f2)
		{
			return f1 < f2;
		}

		++min;
		--max;
	}
	return false;
}

struct Pair
{
	int player1;
	int player2;
	int round1;
	int round2;

	Pair()
	{
		while (canDoSameRound || canDoDiffRound)
		{
			int r1 = rand() % ROUND_COUNT;
			int r2 = rand() % ROUND_COUNT;
			if (r1 == r2)
			{
				if (canDoSameRound)
				{
					for (int i = 0; i < 10000; ++i)
					{
						int p1 = rand() % PLAYER_COUNT;
						int r = rand() % ROUND_COUNT;
						int p2 = rand() % PLAYER_COUNT;
						int t1 = currentSittings[p1][r];
						int t2 = currentSittings[p2][r];
						while (t1 == t2)
						{
							p2 = rand() % PLAYER_COUNT;
							t2 = currentSittings[p2][r];
						}

						if (isSittingValid(p1, r, t2) && isSittingValid(p2, r, t1))
						{
							player1 = p1;
							player2 = p2;
							round1 = r;
							round2 = r;
							return;
						}
					}
					canDoSameRound = false;
				}
			}
			else if (canDoDiffRound)
			{
				for (int i = 0; i < 10000; ++i)
				{
					int p1 = rand() % PLAYER_COUNT;
					int r1 = rand() % ROUND_COUNT;
					int r2 = rand() % ROUND_COUNT;
					int t1 = currentSittings[p1][r1];
					int t2 = currentSittings[p1][r2];
					bool found = false;
					for (int j = 0; j < ROUND_COUNT * 10; ++j)
					{
						if (t1 != t2)
						{
							found = true;
							break;
						}
						r2 = rand() % ROUND_COUNT;
						t2 = currentSittings[p1][r2];
					}
					if (!found)
					{
						--i;
						continue;
					}

					int candidates[PLAYER_COUNT];
					int candidatesCount = 0;
					for (int p = 0; p < PLAYER_COUNT; ++p)
					{
						if (currentSittings[p][r2] == t1 && currentSittings[p][r1] == t2)
						{
							candidates[candidatesCount++] = p;
						}
					}

					if (candidatesCount > 0)
					{
						player1 = p1;
						player2 = candidates[rand() % candidatesCount];
						round1 = r1;
						round2 = r2;
						return;
					}
				}
				canDoDiffRound = false;
			}
		}
		throw "Can not find pair";
	}

	void swap()
	{
		if (round1 == round2)
		{
			int t1 = currentSittings[player1][round1];
			int t2 = currentSittings[player2][round1];
			currentSittings[player1][round1] = t2;
			currentSittings[player2][round1] = t1;
		}
		else if (player1 == player2)
		{
			int t1 = currentSittings[player1][round1];
			int t2 = currentSittings[player1][round2];
			currentSittings[player1][round1] = t2;
			currentSittings[player1][round2] = t1;
		}
		else
		{
			int t1 = currentSittings[player1][round1];
			int t2 = currentSittings[player1][round2];
			currentSittings[player2][round2] = t2;
			currentSittings[player1][round1] = t2;
			currentSittings[player1][round2] = t1;
			currentSittings[player2][round1] = t1;
		}
	}
};

void shuffle()
{
	memset(currentSittings, 0, PLAYER_COUNT * ROUND_COUNT * sizeof(int));

	int totalGamesCount = 0;
	int gamesCount[PLAYER_COUNT];
	memset(gamesCount, 0, PLAYER_COUNT * sizeof(int));
	for (int r = 0; r < ROUND_COUNT - 1; ++r)
	{
		for (int t = 1; t < TABLE_COUNT + 1; ++t)
		{
			for (int i = 0; i < 10; ++i)
			{
				int p = rand() % PLAYER_COUNT;
				while (currentSittings[p][r] != 0)
				{
					p = rand() % PLAYER_COUNT;
				}
				++gamesCount[p];
				currentSittings[p][r] = t;
			}
			++totalGamesCount;
		}
	}

	while (totalGamesCount < PLAYER_COUNT * GAMES_PER_PLAYER / 10)
	{
		int t = PLAYER_COUNT * GAMES_PER_PLAYER / 10 - totalGamesCount;
		for (int i = 0; i < 10; ++i)
		{
			int p = rand() % PLAYER_COUNT;
			while (currentSittings[p][ROUND_COUNT - 1] != 0)
			{
				p = rand() % PLAYER_COUNT;
			}
			++gamesCount[p];
			currentSittings[p][ROUND_COUNT - 1] = t;
		}
		++totalGamesCount;
	}


	// make sure all play the same number of games
	for (int p = 0; p < PLAYER_COUNT; ++p)
	{
		while (gamesCount[p] != GAMES_PER_PLAYER)
		{
			if (gamesCount[p] > GAMES_PER_PLAYER)
			{
				int minP = p + 1;
				for (int p1 = p + 2; p1 < PLAYER_COUNT; ++p1)
				{
					if (gamesCount[p1] < gamesCount[minP])
					{
						minP = p1;
					}
				}

				for (int count = gamesCount[minP]; minP < PLAYER_COUNT; ++minP)
				{
					if (gamesCount[minP] != count)
					{
						continue;
					}

					int r = 0;
					for (; r < ROUND_COUNT; ++r)
					{
						if (currentSittings[p][r] > 0 && currentSittings[minP][r] == 0)
						{
							currentSittings[minP][r] = currentSittings[p][r];
							currentSittings[p][r] = 0;
							++gamesCount[minP];
							--gamesCount[p];
							break;
						}
					}

					if (r < ROUND_COUNT)
					{
						break;
					}
				}
			}
			else
			{
				int maxP = p + 1;
				for (int p1 = p + 2; p1 < PLAYER_COUNT; ++p1)
				{
					if (gamesCount[p1] > gamesCount[maxP])
					{
						maxP = p1;
					}
				}

				for (int count = gamesCount[maxP]; maxP < PLAYER_COUNT; ++maxP)
				{
					if (gamesCount[maxP] != count)
					{
						continue;
					}

					int r = 0;
					for (; r < ROUND_COUNT; ++r)
					{
						if (currentSittings[p][r] == 0 && currentSittings[maxP][r] > 0)
						{
							currentSittings[p][r] = currentSittings[maxP][r];
							currentSittings[maxP][r] = 0;
							--gamesCount[maxP];
							++gamesCount[p];
							break;
						}
					}

					if (r < ROUND_COUNT)
					{
						break;
					}
				}
			}
		}
	}


	// make sure player evenly plays on each table
	int tableFreqs[PLAYER_COUNT][TABLE_COUNT];
	for (int p = 0; p < PLAYER_COUNT; ++p)
	{
		calculateTableFreq(p, tableFreqs[p]);
	}

	for (int p = 0; p < PLAYER_COUNT; ++p)
	{
		while (true)
		{
			int tMin = 0;
			int tMax = 0;
			for (int t = 1; t < TABLE_COUNT; ++t)
			{
				if (tableFreqs[p][t] < tableFreqs[p][tMin])
				{
					tMin = t;
				}
				else if (tableFreqs[p][t] > tableFreqs[p][tMax])
				{
					tMax = t;
				}
			}

			if (tableFreqs[p][tMax] > MAX_TABLE_FREQ)
			{
				int count = GAMES_PER_PLAYER;
				for (int p1 = p + 1; p1 < PLAYER_COUNT; ++p1)
				{
					if (tableFreqs[p1][tMax] < count)
					{
						count = tableFreqs[p1][tMax];
					}
				}

				for (; count < MAX_TABLE_FREQ; ++count)
				{
					int player;
					for (player = p + 1; player < PLAYER_COUNT; ++player)
					{
						if (tableFreqs[player][tMax] != count)
						{
							continue;
						}

						int r;
						for (r = 0; r < ROUND_COUNT; ++r)
						{
							if (currentSittings[p][r] == tMax + 1 && currentSittings[player][r] == tMin + 1)
							{
								currentSittings[p][r] = tMin + 1;
								currentSittings[player][r] = tMax + 1;
								--tableFreqs[p][tMax];
								++tableFreqs[player][tMax];
								++tableFreqs[p][tMin];
								--tableFreqs[player][tMin];
								break;
							}
						}

						if (r < ROUND_COUNT)
						{
							break;
						}
					}

					if (player < PLAYER_COUNT)
					{
						break;
					}
				}

				if (count >= MAX_TABLE_FREQ)
				{
					break;
				}
			}
			else if (tableFreqs[p][tMin] < MIN_TABLE_FREQ)
			{
				int count = 0;
				for (int p1 = p + 1; p1 < PLAYER_COUNT; ++p1)
				{
					if (tableFreqs[p1][tMin] > count)
					{
						count = tableFreqs[p1][tMin];
					}
				}

				for (; count > MIN_TABLE_FREQ; --count)
				{
					int player;
					for (player = p + 1; player < PLAYER_COUNT; ++player)
					{
						if (tableFreqs[player][tMin] != count)
						{
							continue;
						}

						int r;
						for (r = 0; r < ROUND_COUNT; ++r)
						{
							if (currentSittings[p][r] == tMax + 1 && currentSittings[player][r] == tMin + 1)
							{
								currentSittings[player][r] = tMax + 1;
								currentSittings[p][r] = tMin + 1;
								++tableFreqs[p][tMin];
								--tableFreqs[player][tMin];
								--tableFreqs[p][tMax];
								++tableFreqs[player][tMax];
								break;
							}
						}

						if (r < ROUND_COUNT)
						{
							break;
						}
					}

					if (player < PLAYER_COUNT)
					{
						break;
					}
				}

				if (count <= MIN_TABLE_FREQ)
				{
					break;
				}
			}
			else
			{
				break;
			}
		}
	}
}


int main()
{
	if (TABLE_COUNT * 10 > PLAYER_COUNT)
	{
		printf("Too many tables. Set tables to %i or less\n", PLAYER_COUNT / 10);
	}

	if ((GAMES_PER_PLAYER * PLAYER_COUNT) % 10 != 0)
	{
		printf("(GAMES_PER_PLAYER * PLAYER_COUNT) %% 10 != 0\n");
	}

	try
	{
		srand((int)time(NULL));
		printf("You need %i rounds for %i players playing %i games each.\n", ROUND_COUNT, PLAYER_COUNT, GAMES_PER_PLAYER);
		shuffle();
		calcuatePairFrequences(currentFreq);
		finalizeCurrentSittings();

		int count = 0;
		int shuffleCount = 0;
		while (true)
		{
			Pair pair;
			pair.swap();

			int freq[ROUND_COUNT + 1];
			calcuatePairFrequences(freq);
			if (is1Better(freq, currentFreq))
			{
				for (int i = 0; i <= ROUND_COUNT; ++i)
				{
					currentFreq[i] = freq[i];
				}

				if (is1Better(freq, finalFreq))
				{
					finalizeCurrentSittings();
				}
				count = 0;
			}
			else
			{
				pair.swap();
			}

			if (++count >= 1000000)
			{
				++shuffleCount;
				shuffle();
				calcuatePairFrequences(currentFreq);
				count = 0;
				printf("$$$$$$$$$$$$$$$$$$$$$ %i\n", shuffleCount);
			}
		}
	}
	catch (const char* error)
	{
		printf("ERROR: %s\n", error);
	}
	getchar();
}

