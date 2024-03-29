// Numbers.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "math.h"
#include <stdlib.h>
#include <time.h>

#define PLAYER_COUNT 30
#define GAMES_PER_PLAYER 10
#define TABLE_COUNT 3

#define ROUND_COUNT ((PLAYER_COUNT * GAMES_PER_PLAYER + 10 * TABLE_COUNT - 1) / (10 * TABLE_COUNT))

int sittings[PLAYER_COUNT][ROUND_COUNT] =
{
{ 2, 2, 2, 1, 2, 1, 1, 2, 2, 1 },
{ 3, 2, 3, 2, 3, 3, 1, 3, 3, 2 },
{ 1, 3, 3, 3, 1, 1, 3, 1, 2, 2 },
{ 1, 1, 1, 2, 2, 2, 3, 2, 1, 3 },
{ 3, 3, 1, 1, 2, 3, 2, 2, 2, 3 },
{ 3, 1, 2, 2, 1, 1, 2, 1, 1, 1 },
{ 1, 3, 2, 2, 1, 1, 1, 2, 2, 3 },
{ 1, 1, 2, 3, 2, 3, 1, 3, 3, 3 },
{ 1, 1, 3, 1, 1, 2, 1, 3, 2, 1 },
{ 3, 3, 1, 2, 2, 1, 3, 3, 3, 1 },
{ 3, 2, 1, 3, 2, 2, 1, 2, 1, 2 },
{ 3, 2, 2, 2, 3, 2, 3, 3, 2, 1 },
{ 2, 1, 1, 2, 3, 3, 2, 1, 2, 2 },
{ 2, 2, 3, 1, 2, 2, 3, 1, 2, 3 },
{ 2, 2, 3, 3, 1, 3, 3, 3, 1, 3 },
{ 3, 3, 3, 1, 3, 3, 1, 2, 1, 1 },
{ 2, 1, 2, 1, 1, 1, 3, 2, 3, 2 },
{ 2, 1, 3, 2, 3, 1, 2, 2, 1, 3 },
{ 1, 2, 1, 3, 1, 3, 2, 1, 3, 1 },
{ 1, 3, 2, 1, 3, 2, 2, 3, 1, 2 },
{ 1, 2, 1, 1, 3, 1, 2, 3, 3, 3 },
{ 2, 3, 3, 2, 2, 2, 2, 1, 3, 2 },
{ 2, 3, 1, 1, 1, 3, 3, 3, 1, 2 },
{ 3, 1, 1, 1, 1, 2, 1, 1, 3, 3 },
{ 3, 3, 2, 3, 3, 2, 3, 1, 2, 3 },
{ 1, 1, 3, 3, 3, 3, 3, 2, 3, 1 },
{ 1, 2, 2, 2, 2, 3, 1, 1, 1, 2 },
{ 2, 2, 2, 3, 1, 2, 2, 2, 3, 1 },
{ 3, 1, 3, 3, 2, 1, 2, 3, 2, 2 },
{ 2, 3, 1, 3, 3, 1, 1, 1, 1, 1 }
};

int numbers[PLAYER_COUNT][ROUND_COUNT];
int deviation;

void printNumbers()
{
	for (int p = 0; p < PLAYER_COUNT; ++p)
	{
		for (int r = 0; r < ROUND_COUNT - 1; ++r)
		{
			if (sittings[p][r] > 0)
			{
				printf("%i,%i,", sittings[p][r], numbers[p][r]);
			}
			else
			{
				printf(",,");
			}
		}

		if (sittings[p][ROUND_COUNT - 1] > 0)
		{
			printf("%i,%i\n", sittings[p][ROUND_COUNT - 1], numbers[p][ROUND_COUNT - 1]);
		}
		else
		{
			printf(",\n");
		}
	}
	printf("................................\n%i\n-------------------------------------------------------------------\n", deviation);
}

int calculateDeviation(int player)
{
	int freq[10] = { 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 };
	int freqZ[5] = { 0, 0, 0, 0, 0 };
	int roundCount = 0;
	for (int r = 0; r < ROUND_COUNT; ++r)
	{
		int n = numbers[player][r] - 1;
		if (n >= 0)
		{
			++freq[n];
			++freqZ[n / 2];
		}
	}

	int max_freq = (GAMES_PER_PLAYER + 9) / 10;
	int max_freqZ = (GAMES_PER_PLAYER + 4) / 5;
	int result = 0;
	int i = 0;
	while (i < 5)
	{
		if (freq[i] > max_freq)
		{
			result += freq[i] - max_freq;
		}

		if (freqZ[i] > max_freqZ)
		{
			result += freqZ[i] - max_freqZ;
		}
		++i;
	}
	while (i < 10)
	{
		if (freq[i] > max_freq)
		{
			result += freq[i] - max_freq;
		}
		++i;
	}
	return result;
}

void calculateDeviation()
{
	deviation = 0;
	for (int p = 0; p < PLAYER_COUNT; ++p)
	{
		deviation += calculateDeviation(p);
	}
}

void init()
{
	for (int r = 0; r < ROUND_COUNT; ++r)
	{
		int currentNumber[TABLE_COUNT];
		for (int i = 0; i < TABLE_COUNT; ++i)
		{
			currentNumber[i] = 1;
		}

		for (int p = 0; p < PLAYER_COUNT; ++p)
		{
			int n = 0;
			int t = sittings[p][r];
			if (t > 0)
			{
				n = currentNumber[t - 1]++;
			}
			numbers[p][r] = n;
		}
	}
}

void shuffle()
{
	for (int i = 0; i < PLAYER_COUNT * ROUND_COUNT * 2; ++i)
	{
		int r = rand() % ROUND_COUNT;
		int p1 = rand() % PLAYER_COUNT;
		int t = sittings[p1][r];
		while (t == 0)
		{
			p1 = rand() % PLAYER_COUNT;
			t = sittings[p1][r];
		}

		int p2 = rand() % PLAYER_COUNT;
		while (p2 == p1 || t != sittings[p2][r])
		{
			p2 = rand() % PLAYER_COUNT;
		}

		int n = numbers[p1][r];
		numbers[p1][r] = numbers[p2][r];
		numbers[p2][r] = n;
	}
	calculateDeviation();
}

bool next()
{
	for (int r = 0; r < ROUND_COUNT; ++r)
	{
		for (int p1 = 0; p1 < PLAYER_COUNT; ++p1)
		{
			int oldDeviation1 = calculateDeviation(p1);
			if (oldDeviation1 <= 0)
			{
				continue;
			}

			for (int p2 = 0; p2 < p1; ++p2)
			{
				if (sittings[p1][r] == 0 || sittings[p1][r] != sittings[p2][r])
				{
					continue;
				}

				int oldDeviation2 = calculateDeviation(p2);
				int n1 = numbers[p1][r];
				int n2 = numbers[p2][r];
				numbers[p1][r] = n2;
				numbers[p2][r] = n1;
				int newDeviation2 = calculateDeviation(p2);
				if (newDeviation2 > oldDeviation2)
				{
					numbers[p1][r] = n1;
					numbers[p2][r] = n2;
					continue;
				}

				int newDeviation1 = calculateDeviation(p1);
				if (newDeviation1 > oldDeviation1)
				{
					numbers[p1][r] = n1;
					numbers[p2][r] = n2;
					continue;
				}

				if (newDeviation1 == oldDeviation1 && newDeviation2 == oldDeviation2)
				{
					continue;
				}


				deviation += newDeviation1 - oldDeviation1;
				deviation += newDeviation2 - oldDeviation2;
				return true;
			}
		}
	}
	return false;
}

int main()
{
	srand((int)time(NULL));
	memset(numbers, PLAYER_COUNT * ROUND_COUNT * sizeof(int), 0);
	init();
	shuffle();
	printNumbers();
	int bestDeviation = deviation;
	while (true)
	{
		if (deviation < bestDeviation)
		{
			bestDeviation = deviation;
			printNumbers();
			if (bestDeviation == 0)
			{
				break;
			}
		}

		if (!next())
		{
			shuffle();
		}
	}
	getchar();
    return 0;
}

