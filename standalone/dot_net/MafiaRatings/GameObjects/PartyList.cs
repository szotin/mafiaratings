using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class PartyList
    {
        private Dictionary<int, Party> mParties = new Dictionary<int, Party>();

        public const int PAST = 1;
        public const int PRESENT = 2;
        public const int FUTURE = 4;

        public PartyList()
        {
            mParties.Add(-Database.Club.Id, new Party());
        }

        public PartyList(DataReader reader, int version = Database.VERSION)
        {
            int count = reader.NextInt();
            for (int i = 0; i < count; ++i)
            {
                Party party = new Party(reader, version);
                mParties.Add(party.Id, party);
            }
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mParties.Count);
            foreach (Party party in mParties.Values)
            {
                party.Write(writer);
            }
        }

        public List<Party> Query(int flags)
        {
            List<Party> parties = new List<Party>();
            int now = Timestamp.Now;
            foreach (Party party in mParties.Values)
            {
                if (now >= party.StartTime + party.Duration)
                {
                    if ((flags & PAST) != 0)
                    {
                        parties.Add(party);
                    }
                }
                else if (now >= party.StartTime)
                {
                    if ((flags & PRESENT) != 0)
                    {
                        parties.Add(party);
                    }
                }
                else if ((flags & FUTURE) != 0)
                {
                    parties.Add(party);
                }
            }
            parties.Sort();
            return parties;
        }

        public Party this[int id]
        {
            get
            {
                Party party = null;
                mParties.TryGetValue(id, out party);
                return party;
            }
        }

        public bool Contains(int id)
        {
            return mParties.ContainsKey(id);
        }

        public int Count
        {
            get { return mParties.Count; }
        }

        public Party FindCurrentParty()
        {
            Party result = null;
            int now = Timestamp.Now;
            foreach (Party party in mParties.Values)
            {
                if (party.Id <= 0)
                {
                    result = party;
                }
                else if (party.StartTime <= now && party.StartTime + party.Duration > now)
                {
                    result = party;
                    break;
                }
            }
            return result;
        }
    }
}
